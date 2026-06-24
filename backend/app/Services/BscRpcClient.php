<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class BscRpcClient
{
    private int $id = 0;

    public function blockNumber(): int
    {
        $hex = $this->rpc('eth_blockNumber', []);

        return (int) hexdec(substr($hex, 2));
    }

    /**
     * @param  list<string>  $topics
     * @return array<int, array<string, mixed>>
     */
    public function getLogs(int $fromBlock, int $toBlock, string $address, array $topics): array
    {
        $result = $this->rpc('eth_getLogs', [[
            'fromBlock' => '0x'.dechex($fromBlock),
            'toBlock' => '0x'.dechex($toBlock),
            'address' => $address,
            'topics' => $topics,
        ]]);

        return is_array($result) ? $result : [];
    }

    /** Native BNB balance in wei (decimal string). */
    public function getBalance(string $address): string
    {
        return $this->hexToDec((string) $this->rpc('eth_getBalance', [$address, 'latest']));
    }

    public function getTransactionCount(string $address): int
    {
        return (int) hexdec((string) $this->rpc('eth_getTransactionCount', [$address, 'pending']));
    }

    /** Gas price in wei (decimal string). */
    public function gasPrice(): string
    {
        return $this->hexToDec((string) $this->rpc('eth_gasPrice', []));
    }

    public function sendRawTransaction(string $rawHex): string
    {
        return (string) $this->rpc('eth_sendRawTransaction', [
            str_starts_with($rawHex, '0x') ? $rawHex : '0x'.$rawHex,
        ]);
    }

    public function ethCall(string $to, string $data): string
    {
        return (string) $this->rpc('eth_call', [[
            'to' => $to,
            'data' => str_starts_with($data, '0x') ? $data : '0x'.$data,
        ], 'latest']);
    }

    /** @return array<string, mixed>|null */
    public function getTransactionReceipt(string $txHash): ?array
    {
        $result = $this->rpc('eth_getTransactionReceipt', [$txHash]);

        return is_array($result) ? $result : null;
    }

    /** BEP20 balanceOf (raw integer as decimal string). */
    public function tokenBalanceOf(string $contract, string $address): string
    {
        $data = config('sweep.erc20_balanceof_selector')
            .str_pad(strtolower(ltrim($address, '0x')), 64, '0', STR_PAD_LEFT);

        return $this->hexToDec($this->ethCall($contract, $data));
    }

    private function hexToDec(string $hex): string
    {
        $hex = ltrim($hex, '0x');

        return $hex === '' ? '0' : gmp_strval(gmp_init($hex, 16), 10);
    }

    private function rpc(string $method, array $params): mixed
    {
        $response = Http::timeout(20)
            ->retry(3, 500)
            ->post((string) config('bsc.rpc_url'), [
                'jsonrpc' => '2.0',
                'id' => ++$this->id,
                'method' => $method,
                'params' => $params,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("BSC RPC HTTP error: {$response->status()}");
        }

        $body = $response->json();

        if (isset($body['error'])) {
            throw new RuntimeException('BSC RPC error: '.json_encode($body['error']));
        }

        return $body['result'] ?? null;
    }
}
