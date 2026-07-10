<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->string('provider_verification_id', 100)->nullable()->after('sumsub_applicant_id');
            $table->string('provider_session_id', 100)->nullable()->after('provider_verification_id');

            $table->index('provider_verification_id');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->dropIndex(['provider_verification_id']);
            $table->dropColumn(['provider_verification_id', 'provider_session_id']);
        });
    }
};
