<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CreateWalletAfterKycApproved;
use App\Models\KycProfile;
use App\Models\ManualApproval;
use App\Models\WalletAddress;
use App\Services\UserNotificationService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 2/3: проверка KYC службой безопасности + автосоздание кошелька.
 */
final class KycReviewTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_client_cannot_access_kyc_admin(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->get('/admin/kyc')->assertForbidden();
    }

    public function test_security_officer_sees_pending_profiles(): void
    {
        $officer = $this->createStaff('security_officer');
        $this->makePendingProfile();

        $this->actingAs($officer)->get('/admin/kyc')->assertOk();
    }

    public function test_approval_marks_profile_and_dispatches_wallet_job(): void
    {
        Queue::fake();

        $officer = $this->createStaff('security_officer');
        $profile = $this->makePendingProfile();

        $this->actingAs($officer)
            ->post("/admin/kyc/{$profile->id}/approve", ['comment' => 'Все документы в порядке'])
            ->assertRedirect(route('admin.kyc.show', $profile));

        $profile->refresh();
        $this->assertSame('approved', $profile->status);
        $this->assertSame($officer->id, $profile->reviewed_by);
        $this->assertSame('approved', $profile->user->kyc_status);

        $this->assertDatabaseHas('manual_approvals', [
            'entity_type' => 'kyc_profile',
            'entity_id' => $profile->id,
            'status' => 'approved',
            'approved_by' => $officer->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'kyc.approved',
            'user_id' => $officer->id,
        ]);

        Queue::assertPushed(
            CreateWalletAfterKycApproved::class,
            fn (CreateWalletAfterKycApproved $job) => $job->userId === $profile->user_id,
        );
    }

    public function test_approval_creates_bep20_wallet_end_to_end(): void
    {
        // QUEUE_CONNECTION=sync — job runs immediately after approval.
        $officer = $this->createStaff('security_officer');
        $profile = $this->makePendingProfile();

        $this->actingAs($officer)->post("/admin/kyc/{$profile->id}/approve");

        $wallet = WalletAddress::query()->where('user_id', $profile->user_id)->firstOrFail();

        $this->assertSame('BEP20', $wallet->network);
        $this->assertSame('USDT', $wallet->asset);
        $this->assertSame(0, $wallet->derivation_index);
        // Canonical BIP44 vector for the test mnemonic, m/44'/60'/0'/0/0.
        $this->assertSame('0x9858EfFD232B4033E47d90003D41EC34EcaEda94', $wallet->address);
    }

    public function test_rejection_stores_reason_and_notifies_user_status(): void
    {
        $officer = $this->createStaff('security_officer');
        $profile = $this->makePendingProfile();

        $this->actingAs($officer)
            ->post("/admin/kyc/{$profile->id}/reject", ['reason' => 'Фото нечитаемо'])
            ->assertRedirect(route('admin.kyc.show', $profile));

        $profile->refresh();
        $this->assertSame('rejected', $profile->status);
        $this->assertSame('Фото нечитаемо', $profile->rejection_reason);
        $this->assertSame('rejected', $profile->user->kyc_status);

        $this->assertDatabaseHas('manual_approvals', [
            'entity_type' => 'kyc_profile',
            'entity_id' => $profile->id,
            'status' => 'rejected',
        ]);

        // No wallet for rejected users.
        $this->assertSame(0, WalletAddress::query()->where('user_id', $profile->user_id)->count());
    }

    public function test_rejection_requires_reason(): void
    {
        $officer = $this->createStaff('security_officer');
        $profile = $this->makePendingProfile();

        $this->actingAs($officer)
            ->post("/admin/kyc/{$profile->id}/reject", [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_cannot_approve_profile_that_is_not_pending(): void
    {
        $officer = $this->createStaff('security_officer');
        $profile = $this->makePendingProfile('approved');

        $this->actingAs($officer)
            ->post("/admin/kyc/{$profile->id}/approve")
            ->assertStatus(422);
    }

    public function test_wallet_job_is_noop_when_kyc_not_approved(): void
    {
        $user = $this->createUnverifiedClient();

        (new CreateWalletAfterKycApproved($user->id))->handle(
            app(WalletService::class),
            app(UserNotificationService::class),
        );

        $this->assertSame(0, WalletAddress::query()->count());
    }

    private function makePendingProfile(string $status = 'pending_review'): KycProfile
    {
        $user = $this->createUnverifiedClient(['kyc_status' => $status]);

        $profile = KycProfile::query()->create([
            'user_id' => $user->id,
            'first_name' => 'Иван',
            'last_name' => 'Иванов',
            'document_type' => 'id_card',
            'document_number' => '123456789',
            'status' => $status,
            'submitted_at' => now(),
        ]);

        ManualApproval::query()->create([
            'entity_type' => 'kyc_profile',
            'entity_id' => $profile->id,
            'required_role' => 'security_officer',
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        return $profile;
    }
}
