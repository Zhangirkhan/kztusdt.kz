<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\WalletAddress;
use App\Models\WalletCounter;
use App\Services\Tron\TronAddressService;
use App\Support\NetworkRegistry;
use Elliptic\EC;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use kornrunner\Keccak;
use RuntimeException;

/**
 * Custodial HD wallet service shared across all supported networks.
 *
 * Implements BIP39 seed + BIP32/BIP44 hardened/normal child derivation manually
 * on top of secp256k1 (simplito/elliptic-php), avoiding heavy unmaintained deps.
 * The same secp256k1 key material is reused per network; only the BIP44 coin type
 * (base path) and the final address encoding differ:
 *   - "evm"  -> EIP-55 0x address  (BEP20, coin type 60)
 *   - "tron" -> Base58Check T...    (TRC20, coin type 195)
 *
 * Verified against the canonical BIP44 Ethereum test vector (see `wallet:verify`).
 */
final class WalletService
{
    private const SECP256K1_N = 'fffffffffffffffffffffffffffffffebaaedce6af48a03bbfd25e8cd0364141';

    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly TronAddressService $tronAddressService,
    ) {}

    /**
     * Ensure the user has a deposit address on every enabled network.
     *
     * @return Collection<int, WalletAddress>
     */
    public function ensureWalletsForUser(User $user): Collection
    {
        return collect(NetworkRegistry::enabledCodes())
            ->map(fn (string $network): WalletAddress => $this->ensureWalletForUser($user, $network));
    }

    public function ensureWalletForUser(User $user, ?string $network = null): WalletAddress
    {
        $network ??= (string) config('wallet.network');
        $asset = NetworkRegistry::exists($network)
            ? NetworkRegistry::asset($network)
            : (string) config('wallet.asset');

        $existing = WalletAddress::query()
            ->where('user_id', $user->id)
            ->where('network', $network)
            ->where('asset', $asset)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $network, $asset): WalletAddress {
            $counter = WalletCounter::query()
                ->lockForUpdate()
                ->firstOrCreate(['network' => $network], ['current_index' => 0]);

            $index = $counter->current_index;
            $address = $this->deriveAddress($index, $network);

            $counter->update(['current_index' => $index + 1]);

            $wallet = WalletAddress::query()->create([
                'user_id' => $user->id,
                'network' => $network,
                'asset' => $asset,
                'address' => $address,
                'derivation_index' => $index,
                'derivation_path' => 'm/'.$this->fullPath($index, $network),
                'is_active' => true,
            ]);

            $this->auditLogService->log(
                action: 'wallet.created',
                userId: $user->id,
                entityType: 'wallet_address',
                entityId: $wallet->id,
                payload: [
                    'network' => $network,
                    'asset' => $asset,
                    'address' => $address,
                    'index' => $index,
                ],
            );

            return $wallet;
        });
    }

    /**
     * Derive the deposit address for a given HD index on the given network.
     */
    public function deriveAddress(int $index, ?string $network = null): string
    {
        $network ??= (string) config('wallet.network');

        return $this->formatAddress($this->derive($this->fullPath($index, $network))['hash'], $network);
    }

    /**
     * Raw private key (hex, no 0x) for a user deposit address by HD index.
     * SECURITY: only call inside the trusted wallet/sweeper context; never log the result.
     */
    public function derivePrivateKey(int $index, ?string $network = null): string
    {
        $network ??= (string) config('wallet.network');

        return $this->derive($this->fullPath($index, $network))['private'];
    }

    public function systemAddress(string $path, ?string $network = null): string
    {
        $network ??= (string) config('wallet.network');

        return $this->formatAddress($this->derive($path)['hash'], $network);
    }

    public function systemPrivateKey(string $path): string
    {
        return $this->derive($path)['private'];
    }

    /**
     * Encode a 20-byte keccak hash (40 hex chars) into a network-specific address.
     */
    private function formatAddress(string $hash40, string $network): string
    {
        $format = NetworkRegistry::exists($network)
            ? NetworkRegistry::addressFormat($network)
            : 'evm';

        return match ($format) {
            'tron' => $this->tronAddressService->fromEvmHash($hash40),
            default => $this->toChecksumAddress($hash40),
        };
    }

    /**
     * @return array{private:string, hash:string}
     */
    private function derive(string $path): array
    {
        $mnemonic = (string) config('wallet.mnemonic');

        if ($mnemonic === '') {
            throw new RuntimeException('WALLET_MNEMONIC не задан. Wallet-service не настроен.');
        }

        $seed = $this->bip39Seed($mnemonic, (string) config('wallet.passphrase', ''));
        [$key, $chainCode] = $this->masterKey($seed);

        foreach ($this->componentsOf($path) as $childIndex) {
            [$key, $chainCode] = $this->ckdPriv($key, $chainCode, $childIndex);
        }

        return [
            'private' => bin2hex($key),
            'hash' => $this->hashFromPrivateKey($key),
        ];
    }

    private function fullPath(int $index, ?string $network = null): string
    {
        $network ??= (string) config('wallet.network');
        $basePath = $this->basePathFor($network);

        return trim($basePath, '/').'/'.$index;
    }

    /**
     * The legacy/default network keeps reading config('wallet.base_path') so the
     * `wallet:verify` command and existing test vectors (which override it) work.
     * Other networks use their registry coin-type path.
     */
    private function basePathFor(string $network): string
    {
        if ($network === (string) config('wallet.network')) {
            return (string) config('wallet.base_path');
        }

        return NetworkRegistry::basePath($network);
    }

    /**
     * @return list<int> ordered list of child indices (hardened offset applied)
     */
    private function componentsOf(string $path): array
    {
        $components = [];

        foreach (explode('/', trim($path, '/')) as $part) {
            if ($part === 'm' || $part === '') {
                continue;
            }

            $hardened = str_ends_with($part, "'");
            $number = (int) rtrim($part, "'");
            $components[] = $hardened ? $number + 0x80000000 : $number;
        }

        return $components;
    }

    private function bip39Seed(string $mnemonic, string $passphrase): string
    {
        // BIP39: PBKDF2-HMAC-SHA512, 2048 rounds, salt = "mnemonic" + passphrase. 64-byte raw seed.
        return hash_pbkdf2('sha512', $mnemonic, 'mnemonic'.$passphrase, 2048, 64, true);
    }

    /**
     * @return array{0:string,1:string} [masterPrivateKey, masterChainCode] (raw 32-byte strings)
     */
    private function masterKey(string $seed): array
    {
        $i = hash_hmac('sha512', $seed, 'Bitcoin seed', true);

        return [substr($i, 0, 32), substr($i, 32, 32)];
    }

    /**
     * BIP32 CKDpriv.
     *
     * @return array{0:string,1:string} [childPrivateKey, childChainCode] (raw 32-byte strings)
     */
    private function ckdPriv(string $kpar, string $cpar, int $index): array
    {
        if ($index >= 0x80000000) {
            $data = "\x00".$kpar.pack('N', $index);
        } else {
            $data = $this->compressedPublicKey($kpar).pack('N', $index);
        }

        $i = hash_hmac('sha512', $data, $cpar, true);
        $il = substr($i, 0, 32);
        $ir = substr($i, 32, 32);

        $n = gmp_init(self::SECP256K1_N, 16);
        $ki = gmp_mod(
            gmp_add(gmp_init(bin2hex($il), 16), gmp_init(bin2hex($kpar), 16)),
            $n,
        );

        $kiHex = str_pad(gmp_strval($ki, 16), 64, '0', STR_PAD_LEFT);

        return [hex2bin($kiHex), $ir];
    }

    private function compressedPublicKey(string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate(bin2hex($privateKey));

        return hex2bin($key->getPublic(true, 'hex'));
    }

    /**
     * keccak256(uncompressed pubkey without prefix), last 20 bytes (40 hex chars).
     * Shared by both EVM and TRON address encoders.
     */
    private function hashFromPrivateKey(string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate(bin2hex($privateKey));

        // Uncompressed public key: '04' || X(32) || Y(32). Drop the prefix for keccak.
        $uncompressed = $key->getPublic(false, 'hex');
        $xy = substr($uncompressed, 2);

        $hash = Keccak::hash(hex2bin($xy), 256);

        return substr($hash, -40);
    }

    /**
     * EIP-55 mixed-case checksum encoding.
     */
    private function toChecksumAddress(string $address): string
    {
        $address = strtolower($address);
        $hash = Keccak::hash($address, 256);

        $checksummed = '';

        for ($i = 0, $len = strlen($address); $i < $len; $i++) {
            $checksummed .= hexdec($hash[$i]) >= 8
                ? strtoupper($address[$i])
                : $address[$i];
        }

        return '0x'.$checksummed;
    }
}
