<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->text('eds_challenge')->nullable()->after('company_name');
            $table->timestamp('eds_challenge_expires_at')->nullable()->after('eds_challenge');
            $table->timestamp('eds_verified_at')->nullable()->after('eds_challenge_expires_at');
            $table->string('eds_certificate_subject')->nullable()->after('eds_verified_at');
            $table->string('eds_signer_iin', 12)->nullable()->after('eds_certificate_subject');
            $table->string('eds_signer_bin', 12)->nullable()->after('eds_signer_iin');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('eds_verified_at')->nullable()->after('company_name');
            $table->string('eds_certificate_subject')->nullable()->after('eds_verified_at');
            $table->string('representative_iin', 12)->nullable()->after('eds_certificate_subject');
        });
    }

    public function down(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'eds_challenge',
                'eds_challenge_expires_at',
                'eds_verified_at',
                'eds_certificate_subject',
                'eds_signer_iin',
                'eds_signer_bin',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'eds_verified_at',
                'eds_certificate_subject',
                'representative_iin',
            ]);
        });
    }
};
