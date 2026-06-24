<?php

declare(strict_types=1);

namespace App\Services\Tron;

use Elliptic\EC;
use RuntimeException;

/**
 * Builds, signs (offline) and broadcasts TRON transactions via TronGrid.
 *
 * TRON signing = ECDSA secp256k1 over the 32-byte txID (sha256 of raw_data),
 * producing a 65-byte recoverable signature r || s || v (v ∈ {0,1}).
 * SECURITY: receives raw private keys; never log them.
 */
final class TronTxService
{
    public function __construct(private readonly TronGridClient $client) {}

    /**
     * Transfer TRC20 tokens from a signer to a recipient.
     *
     * @param  string  $privateKey  hex (no 0x)
     * @param  string  $amountRaw   decimal raw token units
     * @return string transaction id
     */
    public function sendToken(string $privateKey, string $from, string $contract, string $to, string $amountRaw): string
    {
        $feeLimit = (int) config('tron.fee_limit', 30_000_000);
        $transaction = $this->client->buildTrc20Transfer($from, $contract, $to, $amountRaw, $feeLimit);

        return $this->signAndBroadcast($transaction, $privateKey);
    }

    /**
     * Transfer native TRX (in SUN) from a signer to a recipient.
     *
     * @param  string  $privateKey  hex (no 0x)
     * @param  string  $amountSun   decimal SUN
     * @return string transaction id
     */
    public function sendTrx(string $privateKey, string $from, string $to, string $amountSun): string
    {
        $transaction = $this->client->buildTrxTransfer($from, $to, $amountSun);

        return $this->signAndBroadcast($transaction, $privateKey);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function signAndBroadcast(array $transaction, string $privateKey): string
    {
        $txId = (string) ($transaction['txID'] ?? '');

        if (! preg_match('/^[0-9a-f]{64}$/i', $txId)) {
            throw new RuntimeException('TronGrid вернул транзакцию без корректного txID.');
        }

        $transaction['signature'] = [$this->sign($txId, $privateKey)];

        return $this->client->broadcast($transaction);
    }

    /** Produce a 65-byte (r||s||v) hex signature over the txID digest. */
    private function sign(string $txId, string $privateKey): string
    {
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKey);
        $signature = $key->sign($txId, ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = str_pad(dechex((int) $signature->recoveryParam), 2, '0', STR_PAD_LEFT);

        return $r.$s.$v;
    }
}
