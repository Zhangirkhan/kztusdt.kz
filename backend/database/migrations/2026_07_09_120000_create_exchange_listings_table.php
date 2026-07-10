<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_listings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 20); // sell_usdt | buy_usdt (exchanger perspective)
            $table->string('price_type', 20); // fixed | floating
            $table->decimal('fixed_rate', 20, 8)->nullable();
            $table->decimal('margin_percent', 8, 4)->nullable();
            $table->decimal('total_usdt', 36, 8);
            $table->decimal('remaining_usdt', 36, 8);
            $table->decimal('min_limit_kzt', 20, 2);
            $table->decimal('max_limit_kzt', 20, 2);
            $table->json('payment_methods');
            $table->string('payment_term', 20);
            $table->text('conditions_text')->nullable();
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'direction', 'sort_order']);
            $table->index(['tenant_id']);
        });

        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->foreignId('exchange_listing_id')->nullable()->after('tenant_id')->constrained('exchange_listings')->nullOnDelete();
            $table->string('payment_term', 20)->nullable()->after('rate');
            $table->text('listing_conditions')->nullable()->after('payment_term');
        });
    }

    public function down(): void
    {
        Schema::table('exchange_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('exchange_listing_id');
            $table->dropColumn(['payment_term', 'listing_conditions']);
        });

        Schema::dropIfExists('exchange_listings');
    }
};
