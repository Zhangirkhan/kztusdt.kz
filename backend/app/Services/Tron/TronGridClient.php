<?php

declare(strict_types=1);

namespace App\Services\Tron;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Thin HTTP client over the TronGrid full-node + indexer API.
 *
 * Uses the `visible=true` convention so addresses are passed/returned in the
 * Base58 ("T...") form. Amounts for TRX are in SUN (1 TRX = 1_000_000 SUN);
 * TRC20 token amounts are raw integer units.
 */
final class TronGridClient
{
    public function __construct(private readonly TronAddressService $addresses) {}

    /** Latest full-node block height. */
    public function blockNumber(): int
    {
        $block = $this->post('/wallet/getnowblock', []);

        return (int) ($block['block_header']['raw_data']['number'] ?? 0);
    }

    /**
     * Incoming TRC20 transfers to a given address (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function incomingTrc20Transfers(string $address, string $contract, int $limit): array
    {
        $response = $this->request()->get(
            $this->url("/v1/accounts/{$address}/transactions/trc20"),
            [
                'only_to' => 'true',
                'only_confirmed' => 'true',
                'contract_address' => $contract,
                'limit' => $limit,
            ],
        );

        if (! $response->successful()) {
            throw new RuntimeException("TronGrid HTTP error: {$response->status()}");
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * Transaction info (includes blockNumber + contract receipt) or null if not yet mined.
     *
     * @return array<string, mixed>|null
     */
    public function getTransactionInfoById(string $txid): ?array
    {
        $info = $this->post('/wallet/gettransactioninfobyid', ['value' => $txid]);

        return ! empty($info) && isset($info['id']) ? $info : null;
    }

    /** Native TRX balance in SUN (decimal string). */
    public function getBalanceSun(string $address): string
    {
        $account = $this->post('/wallet/getaccount', ['address' => $address, 'visible' => true]);

        return (string) ($account['balance'] ?? '0');
    }

    /** TRC20 balanceOf (raw integer as decimal string). */
    public function trc20BalanceOf(string $contract, string $address): string
    {
        $param = $this->padAddress($address);

        $result = $this->post('/wallet/triggerconstantcontract', [
            'owner_address' => $address,
            'contract_address' => $contract,
            'function_selector' => 'balanceOf(address)',
            'parameter' => $param,
            'visible' => true,
        ]);

        $hex = $result['constant_result'][0] ?? null;

        if (! is_string($hex) || $hex === '') {
            return '0';
        }

        return gmp_strval(gmp_init($hex, 16), 10);
    }

    /**
     * Build an unsigned TRC20 transfer(address,uint256) transaction.
     *
     * @return array<string, mixed> the `transaction` object (with txID/raw_data)
     */
    public function buildTrc20Transfer(string $owner, string $contract, string $to, string $amountRaw, int $feeLimit): array
    {
        $parameter = $this->padAddress($to).$this->padUint($amountRaw);

        $response = $this->post('/wallet/triggersmartcontract', [
            'owner_address' => $owner,
            'contract_address' => $contract,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => $parameter,
            'fee_limit' => $feeLimit,
            'call_value' => 0,
            'visible' => true,
        ]);

        $ok = $response['result']['result'] ?? false;
        $transaction = $response['transaction'] ?? null;

        if ($ok !== true || ! is_array($transaction)) {
            $message = $this->decodeMessage($response['result']['message'] ?? null);

            throw new RuntimeException('TronGrid не построил TRC20-транзакцию'.($message !== '' ? ": {$message}" : '.'));
        }

        return $transaction;
    }

    /**
     * Build an unsigned native TRX transfer.
     *
     * @return array<string, mixed>
     */
    public function buildTrxTransfer(string $owner, string $to, string $amountSun): array
    {
        $transaction = $this->post('/wallet/createtransaction', [
            'owner_address' => $owner,
            'to_address' => $to,
            'amount' => (int) $amountSun,
            'visible' => true,
        ]);

        if (! isset($transaction['txID'])) {
            $message = $this->decodeMessage($transaction['Error'] ?? ($transaction['message'] ?? null));

            throw new RuntimeException('TronGrid не построил TRX-транзакцию'.($message !== '' ? ": {$message}" : '.'));
        }

        return $transaction;
    }

    /**
     * Broadcast a signed transaction.
     *
     * @param  array<string, mixed>  $signedTransaction
     * @return string transaction id
     */
    public function broadcast(array $signedTransaction): string
    {
        $response = $this->post('/wallet/broadcasttransaction', $signedTransaction);

        if (($response['result'] ?? false) === true && isset($response['txid'])) {
            return (string) $response['txid'];
        }

        // Some node versions omit `result` on success but still return the txid.
        if (isset($response['txid']) && ! isset($response['code'])) {
            return (string) $response['txid'];
        }

        $message = $this->decodeMessage($response['message'] ?? ($response['code'] ?? 'unknown'));

        throw new RuntimeException("TronGrid отклонил транзакцию: {$message}");
    }

    private function padAddress(string $address): string
    {
        $hex = $this->addresses->toHex($address); // 41 + 40 hex

        return str_pad(substr($hex, 2), 64, '0', STR_PAD_LEFT);
    }

    private function padUint(string $amountRaw): string
    {
        return str_pad(gmp_strval(gmp_init($amountRaw, 10), 16), 64, '0', STR_PAD_LEFT);
    }

    private function decodeMessage(mixed $message): string
    {
        if (! is_string($message) || $message === '') {
            return '';
        }

        // TronGrid error messages are often hex-encoded.
        if (ctype_xdigit($message) && strlen($message) % 2 === 0) {
            $decoded = @hex2bin($message);

            if ($decoded !== false && mb_check_encoding($decoded, 'UTF-8')) {
                return $decoded;
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        $response = $this->request()->post($this->url($path), $payload);

        if (! $response->successful()) {
            throw new RuntimeException("TronGrid HTTP error: {$response->status()}");
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(20)->retry(3, 500)->acceptJson();

        $apiKey = (string) config('tron.api_key');

        if ($apiKey !== '') {
            $request = $request->withHeaders(['TRON-PRO-API-KEY' => $apiKey]);
        }

        return $request;
    }

    private function url(string $path): string
    {
        return (string) config('tron.api_url').$path;
    }
}
