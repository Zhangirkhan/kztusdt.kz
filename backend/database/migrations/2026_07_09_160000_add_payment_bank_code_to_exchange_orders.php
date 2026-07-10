<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->string('payment_bank_code', 32)->nullable()->after('payment_term');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->dropColumn('payment_bank_code');
        });
    }
};
