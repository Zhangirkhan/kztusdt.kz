<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10); // buy | sell
            $table->string('status', 40)->default('created');
            $table->string('fiat_currency', 10)->default('KZT');
            $table->string('crypto_asset', 20)->default('USDT');
            $table->string('network', 30)->default('BEP20');
            $table->decimal('rate', 20, 8); // applied KZT per USDT
            $table->decimal('fiat_amount', 20, 2); // buy: KZT paid by user; sell: KZT paid to user
            $table->decimal('crypto_amount', 36, 18); // buy: USDT credited (net); sell: USDT debited (gross)
            $table->decimal('fee_percent', 8, 4);
            $table->decimal('fee_amount', 36, 18); // fee in USDT
            $table->text('reject_reason')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('kzt_received_at')->nullable();
            $table->timestamp('kzt_sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status']);
            $table->index(['tenant_id']);
        });

        Schema::create('fiat_payment_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exchange_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 30); // user_to_exchange | exchange_to_user
            $table->string('currency', 10)->default('KZT');
            $table->decimal('amount', 20, 2);
            $table->string('bank_name')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_account')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('proof_file_path')->nullable();
            $table->string('proof_original_name')->nullable();
            $table->string('proof_mime_type', 100)->nullable();
            $table->string('status', 30)->default('pending');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['exchange_order_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiat_payment_requests');
        Schema::dropIfExists('exchange_orders');
    }
};
