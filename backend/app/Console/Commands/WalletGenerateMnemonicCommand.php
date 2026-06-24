<?php

declare(strict_types=1);

namespace App\Console\Commands;

use FurqanSiddiqui\BIP39\BIP39;
use Illuminate\Console\Command;

final class WalletGenerateMnemonicCommand extends Command
{
    protected $signature = 'wallet:generate-mnemonic {--words=12 : Number of words (12,15,18,21,24)}';

    protected $description = 'Generate a new BIP39 mnemonic for the custodial HD wallet';

    public function handle(): int
    {
        $wordCount = (int) $this->option('words');
        $mnemonic = BIP39::Generate($wordCount);
        $words = implode(' ', $mnemonic->words);

        $this->newLine();
        $this->info('Сгенерирован BIP39 mnemonic. Добавьте в .env и НИКОГДА не коммитьте:');
        $this->newLine();
        $this->line('WALLET_MNEMONIC="'.$words.'"');
        $this->newLine();
        $this->warn('Сохраните mnemonic в надёжном месте — это доступ ко всем средствам.');

        return self::SUCCESS;
    }
}
