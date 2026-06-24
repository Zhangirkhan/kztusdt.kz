<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_telegram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('telegram_id', 100)->unique();
            $table->string('telegram_username')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_verified')->default(true);
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_telegram_accounts');
    }
};
