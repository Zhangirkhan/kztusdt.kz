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
            $table->timestamp('due_diligence_required_at')->nullable()->after('notification_preferences');
        });

        Schema::create('due_diligence_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('source_of_funds', 64);
            $table->string('source_of_funds_other', 255)->nullable();
            $table->string('occupation', 64);
            $table->string('industry', 64);
            $table->string('industry_other', 255)->nullable();
            $table->string('annual_income', 32);
            $table->string('platform_purpose', 64);
            $table->string('platform_purpose_other', 255)->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_diligence_profiles');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('due_diligence_required_at');
        });
    }
};
