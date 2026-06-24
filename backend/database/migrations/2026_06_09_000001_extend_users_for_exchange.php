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
            $table->string('phone', 50)->nullable()->unique()->after('email');
            $table->boolean('phone_verified')->default(false)->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_verified');
            $table->string('kyc_status', 30)->default('none')->after('phone_verified_at');
            $table->boolean('has_subscription')->default(false)->after('kyc_status');
            $table->unsignedBigInteger('tenant_id')->nullable()->after('has_subscription');
            $table->string('status', 30)->default('active')->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'phone_verified',
                'phone_verified_at',
                'kyc_status',
                'has_subscription',
                'tenant_id',
                'status',
            ]);
        });
    }
};
