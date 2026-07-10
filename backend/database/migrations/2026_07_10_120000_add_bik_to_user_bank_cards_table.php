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
        Schema::table('user_bank_cards', function (Blueprint $table): void {
            $table->string('bik', 11)->nullable()->after('bank_code');
        });

        $catalog = config('banks.catalog', []);

        DB::table('user_bank_cards')
            ->orderBy('id')
            ->chunkById(100, function ($cards) use ($catalog): void {
                foreach ($cards as $card) {
                    $entry = $catalog[$card->bank_code] ?? null;
                    $bik = is_array($entry) ? ($entry['bik'] ?? null) : null;

                    if ($bik !== null) {
                        DB::table('user_bank_cards')
                            ->where('id', $card->id)
                            ->update(['bik' => $bik]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('user_bank_cards', function (Blueprint $table): void {
            $table->dropColumn('bik');
        });
    }
};
