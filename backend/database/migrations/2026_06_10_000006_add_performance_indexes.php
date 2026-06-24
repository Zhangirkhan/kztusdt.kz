<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->index('status');
            $table->index(['status', 'submitted_at']);
        });

        Schema::table('deposits', function (Blueprint $table): void {
            $table->index(['user_id', 'asset', 'id']);
        });

        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'id']);
        });

        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'asset', 'id']);
        });

        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropIndex(['status']);
        });
    }
};
