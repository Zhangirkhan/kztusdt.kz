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
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->boolean('is_default')->default(false);
            $table->string('status', 30)->default('active');
            $table->timestamps();
        });

        DB::table('tenants')->insert([
            'name' => 'KZT-USDT Exchange',
            'slug' => 'default',
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
