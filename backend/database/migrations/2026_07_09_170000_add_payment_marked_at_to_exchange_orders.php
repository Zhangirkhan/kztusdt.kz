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
            $table->timestamp('payment_marked_at')->nullable()->after('payment_bank_code');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->dropColumn('payment_marked_at');
        });
    }
};
