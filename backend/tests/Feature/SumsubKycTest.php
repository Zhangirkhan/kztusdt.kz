<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Внешний KYC-провайдер Sumsub: webhook и fallback на ручную анкету.
 */
final class SumsubKycTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-webhook-secret';

    public function test_kyc_page_falls_back_to_manual_when_sumsub_not_configured(): void
    {
        config(['kyc.provider' => 'sumsub']); // ключи пустые

        $user = $this->createUnverifiedClient();

        $this->actingAs($user)
            ->get('/kyc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('provider', 'manual'));
    }

    public function test_sumsub_token_endpoint_is_unavailable_when_not_configured(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)->post('/kyc/sumsub/token')->assertNotFound();
    }

    public function test_webhook_rejects_missing_signature(): void
    {
        config(['kyc.sumsub.webhook_secret' => self::WEBHOOK_SECRET]);

        $this->postJson('/api/kyc/sumsub/webhook', ['type' => 'applicantReviewed'])
            ->assertStatus(401);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['kyc.sumsub.webhook_secret' => self::WEBHOOK_SECRET]);

        $this->sendWebhook(['type' => 'applicantReviewed'], 'deadbeef')
            ->assertStatus(401);
    }

    public function test_green_review_approves_kyc_and_dispatches_wallet_job(): void
    {
        Queue::fake();
        config(['kyc.sumsub.webhook_secret' => self::WEBHOOK_SECRET]);

        $user = $this->createUnverifiedClient(['kyc_status' => 'pending_review']);

        $profile = KycProfile::query()->create([
            'user_id' => $user->id,
            'provider' => 'sumsub',
            'sumsub_applicant_id' => 'applicant-123',
            'status' => 'pending_review',
        ]);

        $this->sendWebhook([
            'type' => 'applicantReviewed',
            'applicantId' => 'applicant-123',
            'externalUserId' => (string) $user->id,
            'reviewResult' => ['reviewAnswer' => 'GREEN'],
        ])->assertOk();

        $profile->refresh();
        $this->assertSame('approved', $profile->status);
        $this->assertSame('approved', $user->fresh()->kyc_status);

        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.sumsub.approved']);

        Queue::assertPushed(
            CreateWalletAfterKycApproved::class,
            fn (CreateWalletAfterKycApproved $job) => $job->userId === $user->id,
        );
    }

    public function test_red_review_rejects_kyc_with_reason(): void
    {
        Queue::fake();
        config(['kyc.sumsub.webhook_secret' => self::WEBHOOK_SECRET]);

        $user = $this->createUnverifiedClient(['kyc_status' => 'pending_review']);

        KycProfile::query()->create([
            'user_id' => $user->id,
            'provider' => 'sumsub',
            'sumsub_applicant_id' => 'applicant-456',
            'status' => 'pending_review',
        ]);

        $this->sendWebhook([
            'type' => 'applicantReviewed',
            'applicantId' => 'applicant-456',
            'externalUserId' => (string) $user->id,
            'reviewResult' => [
                'reviewAnswer' => 'RED',
                'reviewRejectType' => 'RETRY',
                'moderationComment' => 'Документ просрочен',
            ],
        ])->assertOk();

        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('rejected', $profile->status);
        $this->assertSame('Документ просрочен', $profile->rejection_reason);
        $this->assertSame('rejected', $user->fresh()->kyc_status);

        Queue::assertNotPushed(CreateWalletAfterKycApproved::class);
    }

    public function test_webhook_for_unknown_applicant_is_ignored(): void
    {
        config(['kyc.sumsub.webhook_secret' => self::WEBHOOK_SECRET]);

        $this->sendWebhook([
            'type' => 'applicantReviewed',
            'applicantId' => 'no-such-applicant',
            'externalUserId' => '999999',
            'reviewResult' => ['reviewAnswer' => 'GREEN'],
        ])->assertOk();

        $this->assertSame(0, KycProfile::query()->count());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendWebhook(array $payload, ?string $digest = null): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

        return $this->call(
            'POST',
            '/api/kyc/sumsub/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Content-Type' => 'application/json',
                'X-Payload-Digest' => $digest ?? hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
                'X-Payload-Digest-Alg' => 'HMAC_SHA256_HEX',
            ]),
            $body,
        );
    }
}
