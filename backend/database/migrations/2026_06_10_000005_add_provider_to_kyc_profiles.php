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
            $table->string('provider', 30)->default('manual')->after('user_id');
            $table->string('sumsub_applicant_id', 100)->nullable()->after('provider');

            $table->index('sumsub_applicant_id');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table): void {
            $table->dropColumn(['provider', 'sumsub_applicant_id']);
        });
    }
};
