<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WalletService;
use Illuminate\Console\Command;

final class WalletVerifyCommand extends Command
{
    protected $signature = 'wallet:verify';

    protected $description = 'Verify HD derivation against the known BIP44 Ethereum test vector';

    public function handle(WalletService $walletService): int
    {
        $testMnemonic = 'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon about';
        $expected = '0x9858EfFD232B4033E47d90003D41EC34EcaEda94';

        config([
            'wallet.mnemonic' => $testMnemonic,
            'wallet.passphrase' => '',
            'wallet.base_path' => "44'/60'/0'/0",
        ]);

        $derived = $walletService->deriveAddress(0);

        $this->line("Expected: {$expected}");
        $this->line("Derived:  {$derived}");

        if (strtolower($derived) === strtolower($expected)) {
            $this->info('OK: HD derivation matches BIP44 test vector (including EIP-55 checksum: '.($derived === $expected ? 'yes' : 'case-mismatch').').');

            return self::SUCCESS;
        }

        $this->error('MISMATCH: derivation is incorrect — do NOT use for real funds.');

        return self::FAILURE;
    }
}
