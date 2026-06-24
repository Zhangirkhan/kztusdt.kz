<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Deposit;
use App\Models\User;
use App\Models\WalletAddress;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

final class WalletAdminTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    public function test_security_officer_cannot_see_wallets_page(): void
    {
        $officer = $this->createStaff('security_officer');

        $this->actingAs($officer)->get('/admin/wallets')->assertForbidden();
    }

    public function test_super_admin_sees_wallets_page(): void
    {
        Http::fake([
            '*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x0']),
        ]);

        $admin = $this->createStaff('super_admin');
        $client = $this->createClient(['phone' => '+77071112233']);

        WalletAddress::query()->create([
            'user_id' => $client->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'address' => '0x9858EfFD232B4033E47d90003D41EC34EcaEda94',
            'derivation_index' => 1,
            'derivation_path' => "m/44'/60'/0'/0/1",
            'is_active' => true,
        ]);

        app(LedgerService::class)->creditDeposit(
            $client->id,
            'USDT',
            '100',
            'test',
            1,
            'seed',
        );

        Deposit::query()->create([
            'user_id' => $client->id,
            'wallet_address_id' => WalletAddress::query()->first()->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'tx_hash' => '0xabc1230000000000000000000000000000000000000000000000000000000001',
            'log_index' => 0,
            'from_address' => '0x1111111111111111111111111111111111111111',
            'to_address' => '0x9858EfFD232B4033E47d90003D41EC34EcaEda94',
            'amount' => '50',
            'amount_raw' => '50000000000000000000',
            'block_number' => 100,
            'confirmations' => 12,
            'status' => 'credited',
            'detected_at' => now(),
            'confirmed_at' => now(),
            'credited_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/wallets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Wallets/Index')
                ->has('systemWallets', 2)
                ->where('stats.wallets_total', 1)
                ->where('stats.deposits_total', 1)
                ->has('wallets.data', 1)
                ->has('deposits.data', 1)
            );
    }

    public function test_client_cannot_open_wallets_admin(): void
    {
        $this->actingAs($this->createClient())
            ->get('/admin/wallets')
            ->assertForbidden();
    }

    public function test_search_filters_by_phone(): void
    {
        Http::fake([
            '*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x0']),
        ]);

        $admin = $this->createStaff('super_admin');
        $match = $this->createClient(['phone' => '+77079998877']);
        $other = $this->createClient(['phone' => '+77071112233']);

        foreach ([$match, $other] as $index => $user) {
            WalletAddress::query()->create([
                'user_id' => $user->id,
                'network' => 'BEP20',
                'asset' => 'USDT',
                'address' => '0x'.str_pad((string) ($index + 1), 40, 'a'),
                'derivation_index' => $index + 1,
                'derivation_path' => "m/44'/60'/0'/0/".($index + 1),
                'is_active' => true,
            ]);
        }

        $this->actingAs($admin)
            ->get('/admin/wallets?q=998877')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('wallets.data', 1)
                ->where('wallets.data.0.user.phone', '+77079998877')
            );
    }
}
