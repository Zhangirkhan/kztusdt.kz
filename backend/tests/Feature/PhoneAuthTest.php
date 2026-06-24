<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 1: вход по номеру телефона через Telegram Gateway OTP.
 */
final class PhoneAuthTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_IIN = '900101100014';

    /** @var list<string> */
    private array $sentCodes = [];

    /**
     * Configure the Gateway and capture the OTP codes pushed through it so the
     * tests can replay the exact code the server generated.
     */
    private function fakeGateway(): void
    {
        config(['telegram.gateway.token' => 'test-gateway-token']);

        $this->sentCodes = [];

        Http::fake([
            'gatewayapi.telegram.org/sendVerificationMessage*' => function ($request) {
                $this->sentCodes[] = (string) ($request['code'] ?? '');

                return Http::response(['ok' => true, 'result' => [
                    'request_id' => 'req_'.count($this->sentCodes),
                ]]);
            },
            'gatewayapi.telegram.org/*' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    private function lastCode(): string
    {
        return (string) end($this->sentCodes);
    }

    public function test_root_redirects_to_phone_auth_page(): void
    {
        $this->get('/')->assertRedirect('/auth/phone');
    }

    public function test_start_sends_otp_and_creates_pending_session(): void
    {
        $this->fakeGateway();

        $response = $this->postJson('/api/auth/phone/start', [
            'iin' => self::VALID_IIN,
            'phone' => '8 707 123 45 67',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['login_code', 'phone', 'code_length', 'expires_at', 'status'])
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('phone', '+77071234567');

        $this->assertDatabaseHas('auth_sessions', [
            'phone' => '+77071234567',
            'status' => 'pending',
            'gateway_request_id' => 'req_1',
        ]);

        $this->assertNotEmpty($this->lastCode());
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastCode());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendVerificationMessage')
            && $request['phone_number'] === '+77071234567');
    }

    public function test_start_fails_gracefully_when_gateway_not_configured(): void
    {
        config(['telegram.gateway.token' => null]);

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->assertStatus(422);
    }

    public function test_start_validates_phone(): void
    {
        $this->fakeGateway();

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_start_validates_iin(): void
    {
        $this->fakeGateway();

        // Missing IIN.
        $this->postJson('/api/auth/phone/start', ['phone' => '+77071234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iin']);

        // Wrong length.
        $this->postJson('/api/auth/phone/start', ['iin' => '12345', 'phone' => '+77071234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iin']);

        // Right length, wrong checksum.
        $this->postJson('/api/auth/phone/start', ['iin' => '900101100015', 'phone' => '+77071234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iin']);
    }

    public function test_iin_is_stored_on_session_and_user(): void
    {
        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->json('login_code');

        $this->assertDatabaseHas('auth_sessions', [
            'login_code' => $code,
            'iin' => self::VALID_IIN,
        ]);

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertOk();

        $userId = AuthSession::query()->where('login_code', $code)->value('user_id');
        $this->assertSame(self::VALID_IIN, User::query()->find($userId)->iin);
    }

    public function test_start_is_rate_limited_per_phone(): void
    {
        $this->fakeGateway();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
                ->assertCreated();
        }

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->assertStatus(422);
    }

    public function test_new_start_expires_previous_pending_sessions(): void
    {
        $this->fakeGateway();

        $first = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');
        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->assertCreated();

        $this->assertSame('expired', AuthSession::query()->where('login_code', $first)->value('status'));
    }

    public function test_full_login_flow_with_correct_code(): void
    {
        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $session = AuthSession::query()->where('login_code', $code)->first();
        $this->assertSame('verified', $session->status);
        $this->assertNotNull($session->user_id);
        $this->assertNull($session->code_hash);

        $user = User::query()->find($session->user_id);
        $this->assertTrue($user->phone_verified);
        $this->assertSame('+77071234567', $user->phone);
        $this->assertSame('none', $user->kyc_status);

        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_user_logs_in_with_same_phone(): void
    {
        $this->fakeGateway();

        $existing = $this->createUnverifiedClient(['phone' => '+77071234567', 'phone_verified' => false]);

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->assertSame(1, User::query()->count());
        $this->assertTrue($existing->fresh()->phone_verified);
        $this->assertSame($existing->id, AuthSession::query()->where('login_code', $code)->value('user_id'));
        $this->assertAuthenticatedAs($existing->fresh());
    }

    public function test_wrong_code_is_rejected_and_counts_attempts(): void
    {
        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => '000000'])
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertSame(1, (int) AuthSession::query()->where('login_code', $code)->value('code_attempts'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_session_fails_after_max_attempts(): void
    {
        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/auth/phone/verify/{$code}", ['code' => '111111'])
                ->assertStatus(422);
        }

        $this->assertSame('failed', AuthSession::query()->where('login_code', $code)->value('status'));

        // Even the correct code no longer works once the session is locked.
        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_expired_code_cannot_be_used(): void
    {
        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->travel(6)->minutes();

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertStatus(422);

        $this->assertSame('expired', AuthSession::query()->where('login_code', $code)->value('status'));
        $this->assertGuest();
    }

    public function test_verified_code_cannot_be_reused(): void
    {
        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');
        $otp = $this->lastCode();

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $otp])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $otp])
            ->assertStatus(422);

        $this->assertSame(1, User::query()->count());
    }

    public function test_verified_authenticated_user_sees_inline_kyc_on_wait_page(): void
    {
        config([
            'kyc.provider' => 'sumsub',
            'kyc.sumsub.app_token' => 'test-token',
            'kyc.sumsub.secret_key' => 'test-secret',
        ]);

        $this->fakeGateway();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('kyc.inline_sumsub', true)
            ->assertJsonPath('kyc.needs_verification', true);

        $this->get("/auth/telegram/{$code}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/TelegramWait')
                ->where('initialStep', 'kyc')
                ->where('kyc.inline_sumsub', true));
    }
}
