<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('network', 30)->unique();
            $table->unsignedBigInteger('current_index')->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('network', 30);
            $table->string('asset', 20);
            $table->string('address', 100);
            $table->unsignedBigInteger('derivation_index');
            $table->string('derivation_path', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['network', 'address']);
            $table->unique(['network', 'derivation_index']);
            $table->unique(['user_id', 'network', 'asset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_addresses');
        Schema::dropIfExists('wallet_counters');
    }
};
