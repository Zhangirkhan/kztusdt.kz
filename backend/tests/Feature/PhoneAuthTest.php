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
 * Этап 1: вход по номеру телефона через WhatsApp OTP (otp.kztusdt.kz).
 */
final class PhoneAuthTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_IIN = '900101100014';

    private const OTP_CODE = '123456';

    /** @var array<string, string> */
    private array $otpCodesByPhone = [];

    private function fakeOtp(): void
    {
        config(['otp.token' => 'test-otp-token']);
        $this->otpCodesByPhone = [];

        Http::fake([
            '*/api/otp/send' => function ($request) {
                $phone = (string) $request['phone'];
                $this->otpCodesByPhone[$phone] = self::OTP_CODE;

                return Http::response([
                    'success' => true,
                    'message' => 'OTP отправлен на WhatsApp',
                    'expires_in' => 300,
                ]);
            },
            '*/api/otp/verify' => function ($request) {
                $phone = (string) $request['phone'];
                $code = (string) $request['code'];

                if (($this->otpCodesByPhone[$phone] ?? '') === $code) {
                    return Http::response([
                        'success' => true,
                        'message' => 'Номер подтверждён',
                    ]);
                }

                return Http::response([
                    'success' => false,
                    'message' => 'Неверный или просроченный код',
                ], 422);
            },
        ]);
    }

    private function lastCode(): string
    {
        return self::OTP_CODE;
    }

    public function test_root_redirects_to_locale_home(): void
    {
        $this->get('/')->assertRedirect('/ru/');
    }

    public function test_start_sends_otp_and_creates_pending_session(): void
    {
        $this->fakeOtp();

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
            'gateway_request_id' => null,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/otp/send')
            && $request['phone'] === '77071234567'
            && $request['purpose'] === 'login');
    }

    public function test_start_fails_gracefully_when_otp_not_configured(): void
    {
        config(['otp.token' => null]);

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->assertStatus(422);
    }

    public function test_start_validates_phone(): void
    {
        $this->fakeOtp();

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_start_validates_iin(): void
    {
        $this->fakeOtp();

        $this->postJson('/api/auth/phone/start', ['phone' => '+77071234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iin']);

        $this->postJson('/api/auth/phone/start', ['iin' => '12345', 'phone' => '+77071234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iin']);

        $this->postJson('/api/auth/phone/start', ['iin' => '900101100015', 'phone' => '+77071234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['iin']);
    }

    public function test_iin_is_stored_on_session_and_user(): void
    {
        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', [
            'client_type' => 'individual',
            'iin' => self::VALID_IIN,
            'phone' => '+77071234567',
        ])
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

    public function test_legal_entity_bin_and_company_name_are_taken_from_eds(): void
    {
        config([
            'ncanode.legal_entity_eds_required' => true,
            'ncanode.skip_verification' => true,
        ]);

        $this->fakeOtp();

        $code = $this->postJson('/api/auth/legal-entity/eds/start', [
            'phone' => '+77079876543',
        ])->assertCreated()->json('login_code');

        $this->postJson("/api/auth/legal-entity/eds/{$code}/verify", [
            'cms' => base64_encode(str_repeat('test-signature-payload-', 4)),
        ])->assertOk();

        $this->assertDatabaseHas('auth_sessions', [
            'login_code' => $code,
            'client_type' => 'legal_entity',
            'bin' => '900101000008',
            'company_name' => 'Тест Юрлицо',
        ]);

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertOk();

        $user = User::query()->findOrFail(
            AuthSession::query()->where('login_code', $code)->value('user_id'),
        );

        $this->assertSame('legal_entity', $user->client_type);
        $this->assertSame('900101000008', $user->bin);
        $this->assertSame('Тест Юрлицо', $user->company_name);
        $this->assertNull($user->iin);
        $this->assertNotNull($user->eds_verified_at);
    }

    public function test_start_reuses_pending_session_without_new_otp(): void
    {
        $this->fakeOtp();

        $first = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->assertCreated()
            ->json('login_code');

        $second = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->assertCreated()
            ->json('login_code');

        $this->assertSame($first, $second);
        $this->assertSame('pending', AuthSession::query()->where('login_code', $first)->value('status'));
        Http::assertSentCount(1);
    }

    public function test_new_start_expires_previous_pending_when_details_change(): void
    {
        $this->fakeOtp();

        $first = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        AuthSession::query()->where('login_code', $first)->update(['iin' => '000000000000']);

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->assertCreated();

        $this->assertSame('expired', AuthSession::query()->where('login_code', $first)->value('status'));
    }

    public function test_authenticated_user_resumes_kyc_from_auth_phone(): void
    {
        config([
            'kyc.provider' => 'sumsub',
            'kyc.sumsub.app_token' => 'test-token',
            'kyc.sumsub.secret_key' => 'test-secret',
        ]);

        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');
        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])->assertOk();

        $this->get('/ru/auth/phone')
            ->assertRedirect(route('auth.whatsapp.wait', [
                'locale' => 'ru',
                'loginCode' => $code,
            ]));
    }

    public function test_start_is_rate_limited_per_phone(): void
    {
        $this->fakeOtp();

        for ($i = 0; $i < 5; $i++) {
            AuthSession::query()
                ->where('phone', '+77071234567')
                ->where('status', 'pending')
                ->update([
                    'status' => 'expired',
                    'expires_at' => now()->subMinute(),
                ]);

            $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
                ->assertCreated();
        }

        AuthSession::query()
            ->where('phone', '+77071234567')
            ->where('status', 'pending')
            ->update([
                'status' => 'expired',
                'expires_at' => now()->subMinute(),
            ]);

        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->assertStatus(422);
    }

    public function test_new_start_expires_previous_pending_sessions(): void
    {
        $this->fakeOtp();

        $first = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');
        $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->assertCreated();

        $this->assertSame('pending', AuthSession::query()->where('login_code', $first)->value('status'));
    }

    public function test_resend_refreshes_otp_for_existing_session(): void
    {
        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])
            ->json('login_code');

        $this->postJson("/api/auth/phone/resend/{$code}")
            ->assertOk()
            ->assertJsonPath('login_code', $code);

        Http::assertSentCount(2);
    }

    public function test_resend_reactivates_expired_session(): void
    {
        $this->fakeOtp();

        $session = AuthSession::query()->create([
            'phone' => '+77071234567',
            'iin' => self::VALID_IIN,
            'login_code' => 'EXPIREDTESTCODE123456789012345',
            'code_hash' => null,
            'gateway_request_id' => null,
            'status' => 'expired',
            'expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/auth/phone/resend/{$session->login_code}")
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('auth_sessions', [
            'login_code' => $session->login_code,
            'status' => 'pending',
        ]);
    }

    public function test_full_login_flow_with_correct_code(): void
    {
        $this->fakeOtp();

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
        $this->fakeOtp();

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
        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => '000000'])
            ->assertStatus(422);

        $this->assertGuest();
        $this->assertSame(1, (int) AuthSession::query()->where('login_code', $code)->value('code_attempts'));
        $this->assertSame(0, User::query()->count());
    }

    public function test_session_fails_after_max_attempts(): void
    {
        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/auth/phone/verify/{$code}", ['code' => '111111'])
                ->assertStatus(422);
        }

        $this->assertSame('failed', AuthSession::query()->where('login_code', $code)->value('status'));

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertStatus(422);

        $this->assertGuest();
    }

    public function test_expired_code_cannot_be_used(): void
    {
        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->travel(6)->minutes();

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertStatus(422);

        $this->assertSame('expired', AuthSession::query()->where('login_code', $code)->value('status'));
        $this->assertGuest();
    }

    public function test_verified_code_cannot_be_reused(): void
    {
        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');
        $otp = $this->lastCode();

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $otp])
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $otp])
            ->assertStatus(422);

        $this->assertSame(1, User::query()->count());
    }

    public function test_phone_page_store_redirects_to_whatsapp_wait_page(): void
    {
        $this->fakeOtp();

        $this->get('/ru/auth/captcha')->assertOk();

        $response = $this->withSession(['auth.captcha_code' => 'abc12'])
            ->post('/ru/auth/phone', [
                'iin' => self::VALID_IIN,
                'phone' => '+77071234567',
                'captcha' => 'ABC12',
            ]);

        $session = AuthSession::query()->where('phone', '+77071234567')->latest('id')->first();
        $this->assertNotNull($session);

        $response->assertRedirect(route('auth.whatsapp.wait', [
            'locale' => 'ru',
            'loginCode' => $session->login_code,
        ]));

        $this->get(route('auth.whatsapp.wait', [
            'locale' => 'ru',
            'loginCode' => $session->login_code,
        ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/WhatsAppWait')
                ->where('loginCode', $session->login_code)
                ->where('initialStep', 'whatsapp'));
    }

    public function test_phone_page_store_requires_valid_captcha(): void
    {
        $this->fakeOtp();

        $this->withSession(['auth.captcha_code' => 'abc12'])
            ->post('/ru/auth/phone', [
                'iin' => self::VALID_IIN,
                'phone' => '+77071234567',
                'captcha' => 'wrong',
            ])
            ->assertSessionHasErrors('captcha');

        $this->assertDatabaseMissing('auth_sessions', [
            'phone' => '+77071234567',
        ]);
    }

    public function test_verified_authenticated_user_sees_inline_kyc_on_wait_page(): void
    {
        config([
            'kyc.provider' => 'sumsub',
            'kyc.sumsub.app_token' => 'test-token',
            'kyc.sumsub.secret_key' => 'test-secret',
        ]);

        $this->fakeOtp();

        $code = $this->postJson('/api/auth/phone/start', ['iin' => self::VALID_IIN, 'phone' => '+77071234567'])->json('login_code');

        $this->postJson("/api/auth/phone/verify/{$code}", ['code' => $this->lastCode()])
            ->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('kyc.inline_sumsub', true)
            ->assertJsonPath('kyc.needs_verification', true);

        $this->get("/ru/auth/whatsapp/{$code}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/WhatsAppWait')
                ->where('initialStep', 'kyc')
                ->where('kyc.inline_sumsub', true));
    }
}
