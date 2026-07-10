<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_bank_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bank_code', 32);
            $table->string('label');
            $table->string('holder_name');
            $table->string('phone')->nullable();
            $table->string('iban', 20)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'bank_code']);
        });

        // Partial uniques so NULL phone/iban do not collide across cards.
        DB::statement('CREATE UNIQUE INDEX user_bank_cards_user_bank_iban_unique ON user_bank_cards (user_id, bank_code, iban) WHERE iban IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX user_bank_cards_user_bank_phone_unique ON user_bank_cards (user_id, bank_code, phone) WHERE phone IS NOT NULL');

        $this->migrateLegacyRequisites();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_bank_cards');
    }

    private function migrateLegacyRequisites(): void
    {
        $catalog = config('banks.catalog', []);

        DB::table('users')
            ->whereNotNull('bank_account')
            ->where('bank_account', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($catalog): void {
                foreach ($users as $user) {
                    $iban = strtoupper(preg_replace('/\s+/', '', (string) $user->bank_account) ?? '');
                    $holder = trim((string) ($user->bank_holder ?: $user->name ?: 'Получатель'));
                    $bankName = trim((string) ($user->bank_name ?: ''));
                    $bankCode = $this->resolveBankCode($bankName, $catalog);
                    $label = $bankName !== '' && $bankName !== ($catalog[$bankCode] ?? '')
                        ? $bankName
                        : ($catalog[$bankCode] ?? 'Карта');

                    DB::table('user_bank_cards')->insert([
                        'user_id' => $user->id,
                        'bank_code' => $bankCode,
                        'label' => mb_substr($label, 0, 255),
                        'holder_name' => mb_substr($holder !== '' ? $holder : 'Получатель', 0, 255),
                        'phone' => null,
                        'iban' => $iban !== '' ? mb_substr($iban, 0, 20) : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    /**
     * @param  array<string, string>  $catalog
     */
    private function resolveBankCode(string $bankName, array $catalog): string
    {
        $normalized = mb_strtolower($bankName);

        $aliases = [
            'kaspi' => ['kaspi'],
            'bcc' => ['bcc', 'центркредит', 'centercredit', 'банк центркредит', 'center credit'],
            'altyn' => ['altyn', 'алтын'],
            'halyk' => ['halyk', 'халык', 'hалык'],
            'freedom' => ['freedom', 'фридом'],
        ];

        foreach ($aliases as $code => $needles) {
            if (! isset($catalog[$code])) {
                continue;
            }

            foreach ($needles as $needle) {
                if ($normalized !== '' && str_contains($normalized, $needle)) {
                    return $code;
                }
            }
        }

        return array_key_first($catalog) ?: 'kaspi';
    }
};
