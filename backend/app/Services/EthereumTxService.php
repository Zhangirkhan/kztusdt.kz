<?php

declare(strict_types=1);

namespace App\Services;

use kornrunner\Ethereum\Transaction;

/**
 * Builds and signs legacy (EIP-155) BSC transactions offline, then broadcasts them.
 * SECURITY: receives raw private keys; never log them.
 */
final class EthereumTxService
{
    public function __construct(private readonly BscRpcClient $rpc) {}

    /**
     * Send native BNB from a signer to a recipient.
     *
     * @param  string  $privateKey  hex (no 0x)
     * @param  string  $valueWei    decimal wei
     * @return string transaction hash
     */
    public function sendBnb(string $privateKey, string $fromAddress, string $to, string $valueWei): string
    {
        $nonce = $this->rpc->getTransactionCount($fromAddress);
        $gasPrice = $this->rpc->gasPrice();
        $gasLimit = (int) config('sweep.bnb_transfer_gas_limit', 21000);

        $tx = new Transaction(
            $this->toHex((string) $nonce),
            $this->toHex($gasPrice),
            $this->toHex((string) $gasLimit),
            $this->stripHex($to),
            $this->toHex($valueWei),
            ''
        );

        $raw = $tx->getRaw($privateKey, (int) config('sweep.chain_id', 56));

        return $this->rpc->sendRawTransaction('0x'.$raw);
    }

    /**
     * Send a BEP20 token transfer from a signer to a recipient.
     *
     * @param  string  $privateKey  hex (no 0x)
     * @param  string  $amountRaw   decimal raw token units
     * @return string transaction hash
     */
    public function sendToken(string $privateKey, string $fromAddress, string $contract, string $to, string $amountRaw): string
    {
        $nonce = $this->rpc->getTransactionCount($fromAddress);
        $gasPrice = $this->rpc->gasPrice();
        $gasLimit = (int) config('sweep.transfer_gas_limit', 100000);

        $tx = new Transaction(
            $this->toHex((string) $nonce),
            $this->toHex($gasPrice),
            $this->toHex((string) $gasLimit),
            $this->stripHex($contract),
            '',
            $this->erc20TransferData($to, $amountRaw)
        );

        $raw = $tx->getRaw($privateKey, (int) config('sweep.chain_id', 56));

        return $this->rpc->sendRawTransaction('0x'.$raw);
    }

    /** ERC20 transfer(address,uint256) calldata as a hex string (no 0x). */
    public function erc20TransferData(string $to, string $amountRaw): string
    {
        $selector = (string) config('sweep.erc20_transfer_selector', 'a9059cbb');
        $addr = str_pad(strtolower($this->stripHex($to)), 64, '0', STR_PAD_LEFT);
        $amount = str_pad($this->decToHex($amountRaw), 64, '0', STR_PAD_LEFT);

        return $selector.$addr.$amount;
    }

    private function toHex(string $dec): string
    {
        if ($dec === '' || $dec === '0') {
            return '';
        }

        return $this->decToHex($dec);
    }

    private function decToHex(string $dec): string
    {
        return gmp_strval(gmp_init($dec, 10), 16);
    }

    private function stripHex(string $value): string
    {
        return str_starts_with($value, '0x') ? substr($value, 2) : $value;
    }
}
