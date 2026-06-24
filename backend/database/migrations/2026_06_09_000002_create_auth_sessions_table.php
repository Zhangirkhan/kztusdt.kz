<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 50);
            $table->string('login_code', 100)->unique();
            $table->string('telegram_id', 100)->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_phone', 50)->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['phone', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
