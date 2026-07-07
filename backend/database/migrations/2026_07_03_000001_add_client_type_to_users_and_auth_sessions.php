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
            $table->string('client_type', 20)->default('individual')->after('phone');
            $table->string('bin', 12)->nullable()->after('iin');
            $table->string('company_name')->nullable()->after('bin');
            $table->index('client_type');
            $table->index('bin');
        });

        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->string('client_type', 20)->default('individual')->after('phone');
            $table->string('bin', 12)->nullable()->after('iin');
            $table->string('company_name')->nullable()->after('bin');
        });

        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->dropColumn('company_name');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['client_type']);
            $table->dropIndex(['bin']);
            $table->dropColumn(['client_type', 'bin', 'company_name']);
        });

        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->dropColumn(['client_type', 'bin', 'company_name']);
        });
    }
};
