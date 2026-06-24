<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sweeps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deposit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_address_id')->constrained()->cascadeOnDelete();
            $table->string('network', 30);
            $table->string('asset', 20);
            $table->string('from_address', 100);
            $table->string('to_address', 100);
            $table->decimal('amount', 36, 18);
            $table->string('amount_raw', 80);
            // pending -> waiting_gas -> gas_sent -> sweeping -> swept | failed | manual_review
            $table->string('status', 30)->default('pending');
            $table->string('gas_tx_hash', 100)->nullable();
            $table->string('sweep_tx_hash', 100)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('gas_sent_at')->nullable();
            $table->timestamp('swept_at')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['user_id']);
            $table->unique('deposit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sweeps');
    }
};
