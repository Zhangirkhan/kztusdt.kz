<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('referral_code', 16)->nullable()->unique()->after('locale');
            $table->foreignId('referred_by_user_id')->nullable()->after('referral_code')
                ->constrained('users')->nullOnDelete();
            $table->index('referred_by_user_id');
        });

        Schema::create('user_referral_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->decimal('value', 8, 4)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'type', 'is_active']);
        });

        $existingCodes = [];

        DB::table('users')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$existingCodes): void {
                foreach ($users as $user) {
                    do {
                        $code = Str::upper(Str::random(8));
                    } while (isset($existingCodes[$code]));

                    $existingCodes[$code] = true;

                    DB::table('users')->where('id', $user->id)->update([
                        'referral_code' => $code,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_referral_benefits');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['referred_by_user_id']);
            $table->dropColumn(['referral_code', 'referred_by_user_id']);
        });
    }
};
