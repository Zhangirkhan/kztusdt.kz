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
        if (! Schema::hasTable('order_appeals')) {
            Schema::create('order_appeals', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('exchange_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
                $table->string('side', 20);
                $table->string('reason', 60);
                $table->string('description', 500)->nullable();
                $table->string('status', 20)->default('open');
                $table->text('resolution_note')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
                $table->index(['exchange_order_id']);
                $table->index(['tenant_id']);
            });
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS order_appeals_one_open_per_order ON order_appeals (exchange_order_id) WHERE status = \'open\'',
        );

        if (! Schema::hasTable('order_appeal_attachments')) {
            Schema::create('order_appeal_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_appeal_id')->constrained()->cascadeOnDelete();
                $table->string('file_path');
                $table->string('original_name');
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->timestamps();

                $table->index(['order_appeal_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_appeal_attachments');
        Schema::dropIfExists('order_appeals');
    }
};
