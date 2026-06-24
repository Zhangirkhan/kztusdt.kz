<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('network', 30)->default('BEP20');
            $table->string('asset', 20)->default('USDT');
            $table->string('to_address', 100);
            $table->decimal('amount', 36, 18); // amount sent on-chain
            $table->decimal('fee_amount', 36, 18)->default(0); // service fee (USDT)
            $table->decimal('network_fee', 36, 18)->default(0); // network fee charged to user (USDT)
            $table->decimal('total_debit', 36, 18); // amount + fee_amount + network_fee
            $table->string('status', 40)->default('created');
            $table->string('confirmation_token', 64)->nullable();
            $table->timestamp('telegram_confirmed_at')->nullable();
            $table->boolean('requires_manual_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->string('tx_hash', 100)->nullable();
            $table->timestamp('broadcast_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
