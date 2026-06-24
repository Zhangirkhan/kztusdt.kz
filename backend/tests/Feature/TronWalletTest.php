<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WalletAddress;
use App\Models\WalletCounter;
use App\Models\Withdrawal;
use App\Services\Tron\TronAddressService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * TRC20 (TRON) HD-кошелёк.
 *
 * Адреса сверяются с каноническим BIP44-вектором (coin type 195) для мнемоники
 * "abandon ... about" (m/44'/195'/0'/0/{i}), а Base58Check — с реальным
 * контрактом USDT на TRON.
 */
final class TronWalletTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VECTOR = [
        0 => 'TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH',
        1 => 'TSeJkUh4Qv67VNFwY8LaAxERygNdy6NQZK',
        2 => 'TYJPRrdB5APNeRs4R7fYZSwW3TcrTKw2gx',
    ];

    private const USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private const USDT_CONTRACT_HEX = '41a614f803b6fd780986a42c78ec9c7f77e6ded13c';

    public function test_base58check_encodes_known_tron_contract(): void
    {
        $tron = app(TronAddressService::class);

        $this->assertSame(self::USDT_CONTRACT, $tron->fromHex(self::USDT_CONTRACT_HEX));
        $this->assertSame('41'.substr(self::USDT_CONTRACT_HEX, 2), $tron->toHex(self::USDT_CONTRACT));
        $this->assertTrue($tron->isValid(self::USDT_CONTRACT));
    }

    public function test_tron_address_validation_rejects_evm_and_bad_checksum(): void
    {
        $tron = app(TronAddressService::class);

        $this->assertFalse($tron->isValid('0x9858EfFD232B4033E47d90003D41EC34EcaEda94'));
        // Valid alphabet/length but corrupted checksum.
        $this->assertFalse($tron->isValid('TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdG'));
    }

    public function test_derivation_matches_canonical_tron_test_vector(): void
    {
        $service = app(WalletService::class);

        foreach (self::VECTOR as $index => $expected) {
            $this->assertSame($expected, $service->deriveAddress($index, 'TRC20'));
        }
    }

    public function test_derived_tron_addresses_are_valid_base58check(): void
    {
        $service = app(WalletService::class);
        $tron = app(TronAddressService::class);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($tron->isValid($service->deriveAddress($i, 'TRC20')));
        }
    }

    public function test_ensure_wallets_creates_one_address_per_network(): void
    {
        $user = $this->createClient();

        $wallets = app(WalletService::class)->ensureWalletsForUser($user);

        $this->assertCount(2, $wallets);

        $tron = WalletAddress::query()
            ->where('user_id', $user->id)
            ->where('network', 'TRC20')
            ->firstOrFail();

        $this->assertSame(self::VECTOR[0], $tron->address);
        $this->assertSame("m/44'/195'/0'/0/0", $tron->derivation_path);
        $this->assertSame('USDT', $tron->asset);

        $this->assertSame('0x9858EfFD232B4033E47d90003D41EC34EcaEda94', WalletAddress::query()
            ->where('user_id', $user->id)
            ->where('network', 'BEP20')
            ->value('address'));

        $this->assertSame(1, WalletCounter::query()->where('network', 'TRC20')->value('current_index'));
        $this->assertSame(1, WalletCounter::query()->where('network', 'BEP20')->value('current_index'));
    }

    public function test_each_user_gets_sequential_tron_address(): void
    {
        $service = app(WalletService::class);

        $a = $service->ensureWalletForUser($this->createClient(), 'TRC20');
        $b = $service->ensureWalletForUser($this->createClient(), 'TRC20');

        $this->assertSame(self::VECTOR[0], $a->address);
        $this->assertSame(self::VECTOR[1], $b->address);
    }

    public function test_withdrawal_rejects_evm_address_on_tron_network(): void
    {
        $this->fakeExternalApis();
        $user = $this->createClient();
        $this->giveBalance($user, '200');

        $this->actingAs($user)->post('/withdraw', [
            'network' => 'TRC20',
            'to_address' => '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed',
            'amount' => 10,
        ])->assertSessionHasErrors(['to_address']);

        $this->assertSame(0, Withdrawal::query()->count());
    }

    public function test_withdrawal_to_tron_address_is_created_with_trc20_network(): void
    {
        $this->fakeExternalApis();
        $user = $this->createClient();
        $this->giveBalance($user, '200');

        $this->actingAs($user)->post('/withdraw', [
            'network' => 'TRC20',
            'to_address' => self::VECTOR[1],
            'amount' => 100,
        ])->assertRedirect(route('withdraw'));

        $withdrawal = Withdrawal::query()->firstOrFail();

        $this->assertSame('TRC20', $withdrawal->network);
        $this->assertSame('USDT', $withdrawal->asset);
        $this->assertSame(self::VECTOR[1], $withdrawal->to_address);
        $this->assertSame(Withdrawal::STATUS_PENDING_REVIEW, $withdrawal->status);
    }
}
