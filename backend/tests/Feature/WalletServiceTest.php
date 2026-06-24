<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WalletAddress;
use App\Models\WalletCounter;
use App\Services\EvmAddressValidator;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Этап 3: HD-кошелёк (BIP39/BIP44, сеть BEP20).
 *
 * Адреса сверяются с каноническим BIP44-вектором для мнемоники
 * "abandon ... about" (m/44'/60'/0'/0/{i}).
 */
final class WalletServiceTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const VECTOR = [
        0 => '0x9858EfFD232B4033E47d90003D41EC34EcaEda94',
        1 => '0x6Fac4D18c912343BF86fa7049364Dd4E424Ab9C0',
        2 => '0xb6716976A3ebe8D39aCEB04372f22Ff8e6802D7A',
    ];

    public function test_derivation_matches_canonical_bip44_test_vector(): void
    {
        $service = app(WalletService::class);

        foreach (self::VECTOR as $index => $expected) {
            $this->assertSame($expected, $service->deriveAddress($index));
        }
    }

    public function test_derived_addresses_are_eip55_checksummed(): void
    {
        $service = app(WalletService::class);
        $validator = app(EvmAddressValidator::class);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($validator->isValid($service->deriveAddress($i)));
        }
    }

    public function test_ensure_wallet_creates_address_and_increments_counter(): void
    {
        $user = $this->createClient();

        $wallet = app(WalletService::class)->ensureWalletForUser($user);

        $this->assertSame(self::VECTOR[0], $wallet->address);
        $this->assertSame(0, $wallet->derivation_index);
        $this->assertSame("m/44'/60'/0'/0/0", $wallet->derivation_path);
        $this->assertSame(1, WalletCounter::query()->where('network', 'BEP20')->value('current_index'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'wallet.created',
            'user_id' => $user->id,
        ]);
    }

    public function test_ensure_wallet_is_idempotent(): void
    {
        $user = $this->createClient();
        $service = app(WalletService::class);

        $first = $service->ensureWalletForUser($user);
        $second = $service->ensureWalletForUser($user);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, WalletAddress::query()->count());
        $this->assertSame(1, WalletCounter::query()->value('current_index'));
    }

    public function test_each_user_gets_unique_sequential_address(): void
    {
        $service = app(WalletService::class);

        $walletA = $service->ensureWalletForUser($this->createClient());
        $walletB = $service->ensureWalletForUser($this->createClient());

        $this->assertSame(self::VECTOR[0], $walletA->address);
        $this->assertSame(self::VECTOR[1], $walletB->address);
        $this->assertNotSame($walletA->address, $walletB->address);
    }

    public function test_system_address_derivation_for_hot_wallet_path(): void
    {
        $service = app(WalletService::class);

        // Hot wallet path uses the same account, just a different index branch.
        $address = $service->systemAddress("44'/60'/0'/0/0");

        $this->assertSame(self::VECTOR[0], $address);
    }

    public function test_private_key_matches_derived_address(): void
    {
        $service = app(WalletService::class);

        $privateKey = $service->derivePrivateKey(0);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $privateKey);
        // Canonical private key of the test vector index 0.
        $this->assertSame(
            '1ab42cc412b618bdea3a599e3c9bae199ebf030895b039e9db1e30dafb12b727',
            $privateKey,
        );
    }

    public function test_missing_mnemonic_throws(): void
    {
        config(['wallet.mnemonic' => '']);

        $this->expectException(RuntimeException::class);

        app(WalletService::class)->deriveAddress(0);
    }
}
