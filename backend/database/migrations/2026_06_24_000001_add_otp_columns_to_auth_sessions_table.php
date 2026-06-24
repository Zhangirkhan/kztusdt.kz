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
            $table->string('code_hash')->nullable()->after('login_code');
            $table->string('gateway_request_id')->nullable()->after('code_hash');
            $table->unsignedSmallInteger('code_attempts')->default(0)->after('gateway_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('auth_sessions', function (Blueprint $table): void {
            $table->dropColumn(['code_hash', 'gateway_request_id', 'code_attempts']);
        });
    }
};
