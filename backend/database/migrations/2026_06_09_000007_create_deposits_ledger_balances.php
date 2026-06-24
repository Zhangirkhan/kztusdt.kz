<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indexer_states', function (Blueprint $table): void {
            $table->id();
            $table->string('network', 30)->unique();
            $table->unsignedBigInteger('last_scanned_block')->default(0);
            $table->timestamps();
        });

        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_address_id')->constrained()->cascadeOnDelete();
            $table->string('network', 30);
            $table->string('asset', 20);
            $table->string('tx_hash', 100);
            $table->unsignedInteger('log_index');
            $table->string('from_address', 100);
            $table->string('to_address', 100);
            $table->decimal('amount', 36, 18);
            $table->string('amount_raw', 80);
            $table->unsignedBigInteger('block_number');
            $table->unsignedInteger('confirmations')->default(0);
            $table->string('status', 30)->default('detected');
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamps();

            $table->unique(['network', 'tx_hash', 'log_index']);
            $table->index(['status']);
            $table->index(['user_id']);
        });

        Schema::create('balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('asset', 20);
            $table->decimal('available', 36, 18)->default(0);
            $table->decimal('locked', 36, 18)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'asset']);
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account', 50);
            $table->string('asset', 20);
            $table->decimal('debit', 36, 18)->default(0);
            $table->decimal('credit', 36, 18)->default(0);
            $table->string('ref_type', 50)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'asset']);
            $table->index(['ref_type', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('balances');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('indexer_states');
    }
};
