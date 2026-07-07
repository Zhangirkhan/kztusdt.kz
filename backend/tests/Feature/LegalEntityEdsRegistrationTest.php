<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class LegalEntityEdsRegistrationTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_BIN = '900101000008';

    private const OTP_CODE = '123456';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ncanode.legal_entity_eds_required' => true,
            'ncanode.skip_verification' => true,
            'otp.token' => 'test-otp-token',
        ]);

        Http::fake([
            '*/api/otp/send' => Http::response([
                'success' => true,
                'message' => 'OTP отправлен на WhatsApp',
                'expires_in' => 300,
            ]),
            '*/api/otp/verify' => Http::response([
                'success' => true,
                'message' => 'Номер подтверждён',
            ]),
        ]);
    }

    public function test_legal_entity_eds_start_creates_pending_session_with_challenge(): void
    {
        $response = $this->postJson('/api/auth/legal-entity/eds/start', [
            'phone' => '+77079876543',
            'bin' => self::VALID_BIN,
            'company_name' => 'ТОО KZT USDT',
        ])->assertCreated();

        $loginCode = $response->json('login_code');

        $this->assertNotEmpty($response->json('challenge_base64'));

        $this->assertDatabaseHas('auth_sessions', [
            'login_code' => $loginCode,
            'client_type' => 'legal_entity',
            'bin' => self::VALID_BIN,
            'company_name' => 'ТОО KZT USDT',
        ]);

        $session = AuthSession::query()->where('login_code', $loginCode)->firstOrFail();
        $this->assertNotNull($session->eds_challenge);
        $this->assertNull($session->eds_verified_at);
    }

    public function test_legal_entity_eds_verify_sends_otp_and_allows_login(): void
    {
        $loginCode = $this->postJson('/api/auth/legal-entity/eds/start', [
            'phone' => '+77079876543',
            'bin' => self::VALID_BIN,
            'company_name' => 'ТОО KZT USDT',
        ])->assertCreated()->json('login_code');

        $this->postJson("/api/auth/legal-entity/eds/{$loginCode}/verify", [
            'cms' => base64_encode(str_repeat('test-signature-payload-', 4)),
        ])->assertOk()->assertJsonStructure(['redirect', 'login_code']);

        $session = AuthSession::query()->where('login_code', $loginCode)->firstOrFail();
        $this->assertNotNull($session->eds_verified_at);

        $this->postJson("/api/auth/phone/verify/{$loginCode}", ['code' => self::OTP_CODE])
            ->assertOk();

        $user = User::query()->findOrFail($session->fresh()->user_id);
        $this->assertSame('legal_entity', $user->client_type);
        $this->assertSame(self::VALID_BIN, $user->bin);
        $this->assertNotNull($user->eds_verified_at);
    }

    public function test_phone_start_rejects_legal_entity_without_eds(): void
    {
        $this->postJson('/api/auth/phone/start', [
            'client_type' => 'legal_entity',
            'bin' => self::VALID_BIN,
            'company_name' => 'ТОО KZT USDT',
            'phone' => '+77079876543',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Для юр. лица сначала подпишите заявку ЭЦП.');
    }
}
