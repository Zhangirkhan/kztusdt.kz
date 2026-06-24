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
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->decimal('fee_percent', 8, 4);
            $table->string('timing')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_subscription')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->after('user_id')
                ->constrained('subscription_plans')
                ->nullOnDelete();
        });

        $now = now();

        DB::table('subscription_plans')->insert([
            [
                'code' => 'standard',
                'name' => 'Сейчас',
                'fee_percent' => config('exchange.fee_default', 0.5),
                'timing' => 'Мгновенно',
                'description' => 'Обмен и вывод USDT в приоритетном порядке.',
                'is_default' => true,
                'is_subscription' => false,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => 'economy',
                'name' => 'Через день',
                'fee_percent' => config('exchange.fee_subscription', 0.05),
                'timing' => 'До 24 часов',
                'description' => 'Если валюта нужна не срочно — минимальная комиссия.',
                'is_default' => false,
                'is_subscription' => true,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $economyPlanId = DB::table('subscription_plans')->where('code', 'economy')->value('id');

        if ($economyPlanId !== null) {
            DB::table('subscriptions')
                ->whereNull('subscription_plan_id')
                ->update(['subscription_plan_id' => $economyPlanId]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('subscription_plan_id');
        });

        Schema::dropIfExists('subscription_plans');
    }
};
