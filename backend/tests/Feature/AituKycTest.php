<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycProfile;
use App\Services\AituKycService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * KYC через Aitu Passport: вердикт верификации приходит в claims id_token.
 */
final class AituKycTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kyc.provider' => 'aitu',
            'kyc.manual_enabled' => false,
            'aitu.client_id' => 'test-client',
            'aitu.client_secret' => 'test-secret',
            'aitu.kyc_scope' => '',
        ]);
    }

    public function test_service_is_enabled_when_aitu_is_active_and_configured(): void
    {
        $this->assertTrue(app(AituKycService::class)->isEnabled());

        config(['aitu.client_secret' => '']);
        $this->assertFalse(app(AituKycService::class)->isEnabled());
    }

    public function test_passed_verdict_approves_kyc_and_dispatches_wallet_job(): void
    {
        Queue::fake();

        $user = $this->createUnverifiedClient();

        $status = app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => 'HIGH',
            'given_name' => 'Аят',
            'family_name' => 'Тестов',
            'iin' => '900101100014',
        ]);

        $this->assertSame('approved', $status);
        $this->assertSame('approved', $user->fresh()->kyc_status);

        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('aitu', $profile->provider);
        $this->assertSame('approved', $profile->status);
        $this->assertSame('Аят', $profile->first_name);
        $this->assertSame('900101100014', $profile->document_number);

        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.aitu.approved']);

        Queue::assertPushed(
            CreateWalletAfterKycApproved::class,
            fn (CreateWalletAfterKycApproved $job): bool => $job->userId === $user->id,
        );
    }

    public function test_failed_verdict_rejects_kyc_without_wallet_job(): void
    {
        Queue::fake();

        $user = $this->createUnverifiedClient();

        $status = app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => 'LOW',
        ]);

        $this->assertSame('rejected', $status);
        $this->assertSame('rejected', $user->fresh()->kyc_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'kyc.aitu.rejected']);

        Queue::assertNotPushed(CreateWalletAfterKycApproved::class);
    }

    public function test_missing_verdict_leaves_status_unchanged(): void
    {
        Queue::fake();

        $user = $this->createUnverifiedClient();

        $status = app(AituKycService::class)->applyFromClaims($user, [
            'sub' => 'abc',
            'phone_number' => $user->phone,
        ]);

        $this->assertSame('none', $status);
        $this->assertSame('none', $user->fresh()->kyc_status);
        $this->assertSame(0, KycProfile::query()->count());

        Queue::assertNotPushed(CreateWalletAfterKycApproved::class);
    }

    public function test_already_approved_user_is_not_reprocessed(): void
    {
        Queue::fake();

        $user = $this->createClient(); // kyc_status = approved

        $status = app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => 'LOW',
        ]);

        $this->assertSame('approved', $status);
        $this->assertSame('approved', $user->fresh()->kyc_status);

        Queue::assertNotPushed(CreateWalletAfterKycApproved::class);
    }

    public function test_verification_claims_and_values_are_configurable(): void
    {
        Queue::fake();

        config([
            'aitu.verification.claims' => ['kyc_state'],
            'aitu.verification.passed_values' => ['ok'],
            'aitu.verification.failed_values' => ['nok'],
        ]);

        $user = $this->createUnverifiedClient();

        $status = app(AituKycService::class)->applyFromClaims($user, ['kyc_state' => 'OK']);

        $this->assertSame('approved', $status);
        $this->assertSame('approved', $user->fresh()->kyc_status);
    }

    public function test_kyc_page_exposes_aitu_provider_and_verify_url(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)
            ->get('/kyc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('provider', 'aitu')
                ->where('manualEnabled', false)
                ->where('showAitu', true)
                ->where('showManualForm', false)
                ->where('aituVerifyUrl', route('auth.aitu.redirect', ['intent' => 'kyc']))
                ->where('aituKycScopeConfigured', false)
                ->etc());
    }

    public function test_gov_doc_verification_json_string_approves_kyc(): void
    {
        Queue::fake();

        $user = $this->createUnverifiedClient();

        $status = app(AituKycService::class)->applyFromClaims($user, [
            'phone' => '77476644108',
            'sessionDocumentId' => 'doc-session-789',
            'sid' => 'sid-789',
            'gov_doc_verification' => json_encode([
                'iin' => '900101100014',
                'firstName' => 'Аят',
                'lastName' => 'Тестов',
            ], JSON_THROW_ON_ERROR),
            'confidence_level' => json_encode(['faceMatch' => 'VERIFIED'], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame('approved', $status);
        $this->assertSame('approved', $user->fresh()->kyc_status);

        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('doc-session-789', $profile->provider_verification_id);
        $this->assertSame('900101100014', $profile->document_number);
    }

    public function test_gov_doc_verification_object_approves_kyc(): void
    {
        Queue::fake();

        $user = $this->createUnverifiedClient();

        $status = app(AituKycService::class)->applyFromClaims($user, [
            'phone' => '77071234567',
            'sessionDocumentId' => 'doc-session-123',
            'sid' => 'sid-456',
            'gov_doc_verification' => [
                'iin' => '900101100014',
                'firstName' => 'Аят',
                'lastName' => 'Тестов',
                'documentNumber' => '123456789',
            ],
        ]);

        $this->assertSame('approved', $status);

        $user->refresh();
        $this->assertSame('approved', $user->kyc_status);
        $this->assertSame('900101100014', $user->iin);
        $this->assertSame('+77071234567', $user->phone);

        $profile = KycProfile::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Аят', $profile->first_name);
        $this->assertSame('900101100014', $profile->document_number);
        $this->assertSame('doc-session-123', $profile->provider_verification_id);
        $this->assertSame('sid-456', $profile->provider_session_id);
        $this->assertNotNull($profile->reviewed_at);
    }

    public function test_confidence_level_face_match_verdict(): void
    {
        Queue::fake();

        $user = $this->createUnverifiedClient();

        $approved = app(AituKycService::class)->applyFromClaims($user, [
            'confidence_level' => ['faceMatch' => 'VERIFIED'],
        ]);
        $this->assertSame('approved', $approved);

        $user2 = $this->createUnverifiedClient();
        $rejected = app(AituKycService::class)->applyFromClaims($user2, [
            'confidence_level' => ['faceMatch' => 'LOW_SIMILARITY'],
        ]);
        $this->assertSame('rejected', $rejected);
    }

    public function test_scope_for_kyc_includes_extra_when_configured(): void
    {
        config(['aitu.scope' => 'openid phone', 'aitu.kyc_scope' => 'CONFIDENCE_LEVEL']);

        $service = app(\App\Services\AituPassportService::class);

        $this->assertSame('openid phone', $service->scopeForIntent('phone'));
        $this->assertSame('openid phone CONFIDENCE_LEVEL', $service->scopeForIntent('kyc'));
        $this->assertTrue($service->kycScopeConfigured());
    }

    public function test_kyc_page_falls_back_to_manual_when_aitu_not_configured(): void
    {
        config(['aitu.client_id' => '', 'aitu.client_secret' => '']);

        $user = $this->createUnverifiedClient();

        $this->actingAs($user)
            ->get('/kyc')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('provider', 'manual')->etc());
    }

    public function test_aitu_callback_error_from_kyc_returns_to_kyc_page(): void
    {
        $user = $this->createUnverifiedClient();

        $this->actingAs($user)
            ->withSession(['aitu.return_to' => 'kyc'])
            ->get('/auth/aitu/callback?error=invalid_request&error_description=client_id_is_not_valid')
            ->assertRedirect(route('kyc'))
            ->assertSessionHasErrors('form');
    }
}
