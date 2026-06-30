<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuthSession;
use App\Models\Deposit;
use App\Models\User;
use App\Models\WalletAddress;
use App\Models\Withdrawal;
use App\Services\DepositConfirmationService;
use App\Services\LedgerService;
use App\Services\PhoneAuthService;
use App\Services\RateService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\ExchangeTestHelpers;
use Tests\TestCase;

/**
 * Regression coverage for the security/correctness hardening pass:
 * idempotent deposits, withdrawal broadcast safety, OTP lockout, rate quoting
 * and Aitu id_token verification.
 */
final class AuditHardeningTest extends TestCase
{
    use ExchangeTestHelpers;
    use RefreshDatabase;

    private const EVM_ADDRESS = '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed';

    public function test_deposit_is_credited_only_once_even_if_indexer_runs_twice(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $wallet = $this->makeWalletAddress($user);
        $deposit = $this->makeConfirmedDeposit($user, $wallet, '25');

        $service = app(DepositConfirmationService::class);
        $ledger = app(LedgerService::class);

        $service->creditConfirmed('BEP20', head: 100, required: 5);
        $service->creditConfirmed('BEP20', head: 100, required: 5);

        $this->assertSame('credited', $deposit->fresh()->status);
        $this->assertSame(0, bccomp('25', $ledger->availableBalance($user->id, 'USDT'), 18));
        $this->assertSame(1, Deposit::query()->where('status', 'credited')->count());
    }

    public function test_deposit_below_required_confirmations_is_not_credited(): void
    {
        $this->fakeExternalApis();

        $user = $this->createClient();
        $wallet = $this->makeWalletAddress($user);
        // block 99, head 100 => 2 confirmations < 5 required.
        $deposit = $this->makeConfirmedDeposit($user, $wallet, '10', blockNumber: 99);

        app(DepositConfirmationService::class)->creditConfirmed('BEP20', head: 100, required: 5);

        $this->assertNotSame('credited', $deposit->fresh()->status);
        $this->assertSame(0, bccomp('0', app(LedgerService::class)->availableBalance($user->id, 'USDT'), 18));
    }

    public function test_otp_locks_out_after_max_attempts_with_atomic_counter(): void
    {
        config(['otp.max_attempts' => 3, 'otp.token' => 'test-token']);

        Http::fake([
            '*/api/otp/verify' => Http::response([
                'success' => false,
                'message' => 'Неверный или просроченный код',
            ], 422),
        ]);

        $session = AuthSession::query()->create([
            'phone' => '+77001234567',
            'login_code' => 'LOGIN-'.uniqid(),
            'code_hash' => null,
            'code_attempts' => 0,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(5),
        ]);

        $service = app(PhoneAuthService::class);

        for ($i = 0; $i < 3; $i++) {
            try {
                $service->verifyCode($session->login_code, '000000');
                $this->fail('Expected wrong code to throw.');
            } catch (RuntimeException) {
                // expected
            }
        }

        $session->refresh();
        $this->assertSame(3, (int) $session->code_attempts);
        $this->assertSame('failed', $session->status);
    }

    public function test_stuck_sending_withdrawal_is_reconciled_and_funds_stay_locked(): void
    {
        config([
            'withdrawal.enabled' => true,
            'sweep.enabled' => false,
            'withdrawal.sending_grace_seconds' => 180,
        ]);
        $this->fakeExternalApis();

        $user = $this->createClient();
        $this->giveBalance($user, '200');

        // A row whose process died mid-broadcast: stuck in "sending" with no tx hash.
        $withdrawal = app(WithdrawalService::class)->create($user, self::EVM_ADDRESS, '100', 'BEP20');
        Withdrawal::query()->whereKey($withdrawal->id)->update([
            'status' => Withdrawal::STATUS_SENDING,
            'tx_hash' => null,
            'updated_at' => now()->subMinutes(10),
        ]);

        app(WithdrawalService::class)->processQueue();

        $withdrawal->refresh();
        $this->assertSame(Withdrawal::STATUS_NEEDS_RECONCILE, $withdrawal->status);

        // Funds must remain locked until a human verifies the chain — never auto-released.
        $ledger = app(LedgerService::class);
        $this->assertSame(0, bccomp('100.51', $ledger->lockedBalance($user->id, 'USDT'), 18));
        $this->assertSame(0, bccomp('99.49', $ledger->availableBalance($user->id, 'USDT'), 18));

        // A second pass must not pick up a needs_reconcile row (no auto double-send).
        app(WithdrawalService::class)->processQueue();
        $this->assertSame(Withdrawal::STATUS_NEEDS_RECONCILE, $withdrawal->fresh()->status);
    }

    public function test_rate_quote_for_order_fails_when_no_real_rate_is_available(): void
    {
        Cache::flush();
        Http::fake([
            'api.binance.com/*' => Http::response([], 500),
            'api.coingecko.com/*' => Http::response([], 500),
        ]);

        $this->expectException(RuntimeException::class);

        app(RateService::class)->quoteForOrder();
    }

    public function test_rate_quote_for_order_returns_marked_up_rate_when_available(): void
    {
        Cache::flush();
        $this->fakeExternalApis(500.0);

        $quote = app(RateService::class)->quoteForOrder();

        $this->assertGreaterThan(0, (float) $quote['buy']);
        $this->assertGreaterThan(0, (float) $quote['sell']);
        $this->assertFalse($quote['stale']);
    }

    public function test_aitu_id_token_signature_is_verified_when_public_key_configured(): void
    {
        $keyPair = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertNotFalse($keyPair);

        $publicKey = openssl_pkey_get_details($keyPair)['key'];
        config(['aitu.id_token.public_key' => $publicKey]);

        $claims = ['phone_number' => '+77001112233', 'exp' => time() + 600];
        $token = $this->signedJwt($claims, $keyPair);

        $service = app(\App\Services\AituPassportService::class);
        $this->assertSame('+77001112233', $service->claimsFromIdToken($token)['phone_number']);

        // Tamper with the payload — signature must now fail closed.
        [$h, $p, $s] = explode('.', $token);
        $tampered = $h.'.'.$this->b64Url(json_encode(['phone_number' => '+70000000000', 'exp' => time() + 600])).'.'.$s;

        $this->expectException(RuntimeException::class);
        $service->claimsFromIdToken($tampered);
    }

    public function test_aitu_id_token_is_rejected_when_expired(): void
    {
        config(['aitu.id_token.public_key' => null, 'aitu.id_token.jwks_uri' => null, 'aitu.id_token.auto_jwks_uri' => false]);

        $token = $this->b64Url(json_encode(['alg' => 'none']))
            .'.'.$this->b64Url(json_encode(['phone_number' => '+77001112233', 'exp' => time() - 100]))
            .'.';

        $this->expectException(RuntimeException::class);
        app(\App\Services\AituPassportService::class)->claimsFromIdToken($token);
    }

    private function makeWalletAddress(User $user): WalletAddress
    {
        return WalletAddress::query()->create([
            'user_id' => $user->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'address' => self::EVM_ADDRESS,
            'derivation_index' => 1,
            'derivation_path' => "m/44'/60'/0'/0/1",
            'is_active' => true,
        ]);
    }

    private function makeConfirmedDeposit(
        User $user,
        WalletAddress $wallet,
        string $amount,
        int $blockNumber = 50,
    ): Deposit {
        return Deposit::query()->create([
            'user_id' => $user->id,
            'wallet_address_id' => $wallet->id,
            'network' => 'BEP20',
            'asset' => 'USDT',
            'tx_hash' => '0x'.bin2hex(random_bytes(16)),
            'log_index' => 0,
            'from_address' => '0x000000000000000000000000000000000000beef',
            'to_address' => $wallet->address,
            'amount' => $amount,
            'amount_raw' => bcmul($amount, bcpow('10', '18', 0), 0),
            'block_number' => $blockNumber,
            'status' => 'detected',
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims
     * @param  \OpenSSLAsymmetricKey  $privateKey
     */
    private function signedJwt(array $claims, $privateKey): string
    {
        $signingInput = $this->b64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$this->b64Url(json_encode($claims));

        $signature = '';
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->b64Url($signature);
    }

    private function b64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
