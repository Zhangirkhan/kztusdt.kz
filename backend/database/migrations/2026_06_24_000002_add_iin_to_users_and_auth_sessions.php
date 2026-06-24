<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('iin', 12)->nullable()->after('phone');
            $table->index('iin');
        });

        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->string('iin', 12)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['iin']);
            $table->dropColumn('iin');
        });

        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->dropColumn('iin');
        });
    }
};
