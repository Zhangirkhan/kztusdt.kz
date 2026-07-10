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
        DB::table('support_messages')->delete();
        DB::table('support_conversations')->delete();

        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropUnique(['user_id']);
            $table->foreignId('exchange_order_id')
                ->after('user_id')
                ->constrained('exchange_orders')
                ->cascadeOnDelete();
            $table->unique('exchange_order_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exchange_order_id');
            $table->unique('user_id');
            $table->dropIndex(['user_id']);
        });
    }
};
