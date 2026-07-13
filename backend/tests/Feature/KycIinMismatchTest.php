<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycProfile;
use App\Services\AituKycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class KycIinMismatchTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const REGISTRATION_IIN = '900101100014';

    private const KYC_IIN = '850215300144';

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    public function test_aitu_mismatch_blocks_wallet_until_confirm(): void
    {
        Queue::fake();

        config([
            'kyc.provider' => 'aitu',
            'kyc.manual_enabled' => false,
            'aitu.client_id' => 'test-client',
            'aitu.client_secret' => 'test-secret',
            'aitu.kyc_scope' => '',
        ]);

        $user = $this->createUnverifiedClient(['iin' => self::REGISTRATION_IIN]);

        app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => 'HIGH',
            'iin' => self::KYC_IIN,
            'given_name' => 'Test',
            'family_name' => 'User',
        ]);

        $user->refresh();
        $this->assertSame('approved', $user->kyc_status);
        $this->assertSame(self::REGISTRATION_IIN, $user->iin);
        $this->assertSame(self::KYC_IIN, $user->kyc_iin);
        $this->assertTrue($user->hasIinMismatch());
        $this->assertFalse($user->canUseWallet());

        $this->actingAs($user)
            ->postJson('/ru/kyc/confirm-iin', ['iin' => '12345'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['iin']);

        $this->actingAs($user)
            ->postJson('/ru/kyc/confirm-iin', ['iin' => self::REGISTRATION_IIN])
            ->assertStatus(422)
            ->assertJsonPath('error', 'ИИН не совпадает с данными из KYC. Введите корректный ИИН.');

        $this->actingAs($user)
            ->postJson('/ru/kyc/confirm-iin', ['iin' => self::KYC_IIN])
            ->assertOk()
            ->assertJsonPath('iin_mismatch', false);

        $user->refresh();
        $this->assertSame(self::KYC_IIN, $user->iin);
        $this->assertFalse($user->hasIinMismatch());
        $this->assertTrue($user->canUseWallet());
    }

    public function test_sumsub_green_with_mismatched_iin_sets_flag(): void
    {
        Queue::fake();

        config([
            'kyc.provider' => 'sumsub',
            'kyc.sumsub.app_token' => 'token',
            'kyc.sumsub.secret_key' => 'secret',
            'kyc.sumsub.webhook_secret' => self::WEBHOOK_SECRET,
        ]);

        $user = $this->createUnverifiedClient([
            'iin' => self::REGISTRATION_IIN,
            'kyc_status' => 'pending_review',
        ]);

        KycProfile::query()->create([
            'user_id' => $user->id,
            'provider' => 'sumsub',
            'sumsub_applicant_id' => 'applicant-iin-1',
            'status' => 'pending_review',
        ]);

        Http::fake([
            '*/resources/applicants/applicant-iin-1/one' => Http::response([
                'info' => [
                    'firstName' => 'Test',
                    'lastName' => 'User',
                    'tin' => self::KYC_IIN,
                    'idDocs' => [
                        ['idDocType' => 'ID_CARD', 'number' => '123456789'],
                    ],
                ],
                'review' => [
                    'reviewStatus' => 'completed',
                    'reviewResult' => ['reviewAnswer' => 'GREEN'],
                ],
            ], 200),
        ]);

        $body = json_encode([
            'type' => 'applicantReviewed',
            'applicantId' => 'applicant-iin-1',
            'externalUserId' => (string) $user->id,
            'reviewResult' => ['reviewAnswer' => 'GREEN'],
        ], JSON_UNESCAPED_UNICODE);

        $this->call(
            'POST',
            '/api/kyc/sumsub/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Content-Type' => 'application/json',
                'X-Payload-Digest' => hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
                'X-Payload-Digest-Alg' => 'HMAC_SHA256_HEX',
            ]),
            $body,
        )->assertOk();

        $user->refresh();
        $this->assertSame('approved', $user->kyc_status);
        $this->assertSame(self::REGISTRATION_IIN, $user->iin);
        $this->assertSame(self::KYC_IIN, $user->kyc_iin);
        $this->assertTrue($user->hasIinMismatch());

        Queue::assertPushed(CreateWalletAfterKycApproved::class);
    }

    public function test_matching_iin_does_not_block_wallet(): void
    {
        Queue::fake();

        config([
            'kyc.provider' => 'aitu',
            'aitu.client_id' => 'test-client',
            'aitu.client_secret' => 'test-secret',
        ]);

        $user = $this->createUnverifiedClient(['iin' => self::KYC_IIN]);

        app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => 'HIGH',
            'iin' => self::KYC_IIN,
        ]);

        $user->refresh();
        $this->assertFalse($user->hasIinMismatch());
        $this->assertTrue($user->canUseWallet());
        $this->assertSame(self::KYC_IIN, $user->kyc_iin);
    }

    public function test_kyc_page_shows_iin_mismatch_flag_after_aitu_approve(): void
    {
        Queue::fake();

        config([
            'kyc.provider' => 'aitu',
            'aitu.client_id' => 'test-client',
            'aitu.client_secret' => 'test-secret',
            'aitu.kyc_scope' => '',
        ]);

        $user = $this->createUnverifiedClient(['iin' => self::REGISTRATION_IIN]);

        app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => 'HIGH',
            'iin' => self::KYC_IIN,
        ]);

        $this->actingAs($user->fresh())
            ->get('/ru/kyc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Kyc')
                ->where('iinMismatch', true)
                ->where('kycStatus', 'approved'));
    }
}
