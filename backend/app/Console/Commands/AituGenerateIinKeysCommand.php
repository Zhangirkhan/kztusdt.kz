<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;

final class AituGenerateIinKeysCommand extends Command
{
    protected $signature = 'aitu:generate-iin-keys {--bits=2048 : RSA key size (2048 recommended; 1024 if required by Aitu)}';

    protected $description = 'Generate an RSA key pair for the Aitu Passport iin_signature parameter';

    public function handle(): int
    {
        $bits = (int) $this->option('bits');

        $resource = openssl_pkey_new([
            'private_key_bits' => $bits,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Не удалось сгенерировать RSA-ключ: '.openssl_error_string());
        }

        if (! openssl_pkey_export($resource, $privateKeyPem)) {
            throw new RuntimeException('Не удалось экспортировать приватный ключ: '.openssl_error_string());
        }

        $details = openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['key'])) {
            throw new RuntimeException('Не удалось получить публичный ключ: '.openssl_error_string());
        }

        $publicKeyPem = (string) $details['key'];
        $inlinePrivate = str_replace("\n", '\n', trim($privateKeyPem));

        $this->newLine();
        $this->info('Публичный RSA-ключ — вставьте в поле «Публичный RSA ключ» консоли Aitu Passport:');
        $this->newLine();
        $this->line(trim($publicKeyPem));

        $this->newLine();
        $this->info('Приватный ключ — добавьте в .env (НИКОГДА не коммитьте):');
        $this->newLine();
        $this->line('AITU_IIN_PRIVATE_KEY="'.$inlinePrivate.'"');

        $this->newLine();
        $this->warn('Подпись ИИН выполняется алгоритмом SHA256withRSA, результат — base64url.');

        return self::SUCCESS;
    }
}
