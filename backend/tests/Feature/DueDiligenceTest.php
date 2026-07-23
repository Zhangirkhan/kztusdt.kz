<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Deposit;
use App\Models\DueDiligenceProfile;
use App\Models\User;
use App\Models\WalletAddress;
use App\Models\Withdrawal;
use App\Services\DepositConfirmationService;
use App\Services\DueDiligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class DueDiligenceTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VALID_ADDRESS = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';

    public function test_large_withdrawal_requires_questionnaire(): void
    {
        $user = $this->preparedClient('20000');

        $this->actingAs($user)->post('/ru/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 10000,
        ])->assertSessionHasErrors(['form']);

        $this->assertSame(0, Withdrawal::query()->count());
    }

    public function test_small_withdrawal_does_not_require_questionnaire(): void
    {
        $user = $this->preparedClient('20000');

        $this->actingAs($user)->post('/ru/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 9999.99,
        ])->assertRedirect(route('wallet', ['tab' => 'withdraw']));

        $this->assertSame(1, Withdrawal::query()->count());
    }

    public function test_user_can_submit_questionnaire_and_withdraw(): void
    {
        $user = $this->preparedClient('20000');

        $this->actingAs($user)->from('/ru/wallet')->post('/ru/due-diligence', $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('due_diligence_profiles', [
            'user_id' => $user->id,
            'source_of_funds' => 'salary',
        ]);

        $this->actingAs($user)->post('/ru/withdraw', [
            'to_address' => self::VALID_ADDRESS,
            'amount' => 10000,
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Withdrawal::query()->count());
    }

    public function test_large_deposit_marks_questionnaire_required(): void
    {
        $user = $this->createClient();
        $wallet = WalletAddress::query()->create([
            'user_id' => $user->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'address' => '0x9858EfFD232B4033E47d90003D41EC34EcaEda94',
            'derivation_index' => 1,
            'derivation_path' => "m/44'/60'/0'/0/1",
            'is_active' => true,
        ]);
        $deposit = Deposit::query()->create([
            'user_id' => $user->id,
            'wallet_address_id' => $wallet->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'tx_hash' => '0xabc1230000000000000000000000000000000000000000000000000000000002',
            'log_index' => 0,
            'from_address' => '0x1111111111111111111111111111111111111111',
            'to_address' => $wallet->address,
            'amount' => '15000',
            'amount_raw' => '15000000000000000000000',
            'block_number' => 100,
            'confirmations' => 0,
            'status' => 'detected',
            'detected_at' => now(),
        ]);

        app(DepositConfirmationService::class)->creditConfirmed('BEP20', $deposit->block_number, 1);

        $user->refresh();
        $this->assertNotNull($user->due_diligence_required_at);
        $this->assertTrue(app(DueDiligenceService::class)->requiresBlockingQuestionnaire($user));
    }

    public function test_submitting_questionnaire_clears_required_flag(): void
    {
        $user = $this->createClient(['due_diligence_required_at' => now()]);

        $this->actingAs($user)->from('/ru/wallet')->post('/ru/due-diligence', $this->validPayload())
            ->assertRedirect();

        $user->refresh();
        $this->assertNull($user->due_diligence_required_at);
        $this->assertTrue(DueDiligenceProfile::query()->where('user_id', $user->id)->exists());
    }

    public function test_admin_can_view_due_diligence_on_user_show(): void
    {
        $user = $this->createClient();
        DueDiligenceProfile::query()->create([
            'user_id' => $user->id,
            'source_of_funds' => 'salary',
            'occupation' => 'employee',
            'industry' => 'it',
            'annual_income' => '10k_50k',
            'platform_purpose' => 'spot_trading',
            'submitted_at' => now(),
        ]);

        $admin = $this->createStaff('super_admin');

        $this->actingAsAdmin($admin)
            ->get('/admin/users/'.$user->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users/Show')
                ->where('user.due_diligence.submitted', true)
                ->where('user.due_diligence.profile.source_of_funds', 'salary')
                ->where('user.due_diligence.profile.occupation', 'employee')
                ->where('user.due_diligence.profile.industry', 'it')
                ->where('user.due_diligence.profile.annual_income', '10k_50k')
                ->where('user.due_diligence.profile.platform_purpose', 'spot_trading'));
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'source_of_funds' => 'salary',
            'occupation' => 'employee',
            'industry' => 'it',
            'annual_income' => '10k_50k',
            'platform_purpose' => 'spot_trading',
        ];
    }

    private function preparedClient(string $balance): User
    {
        $this->enableTelegram();
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->linkTelegram($user, '777000111');
        $this->giveBalance($user, $balance);

        return $user;
    }
}
