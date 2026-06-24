<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kyc_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->unique(['kyc_profile_id', 'type']);
        });

        Schema::create('manual_approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('entity_type', 100);
            $table->unsignedBigInteger('entity_id');
            $table->string('required_role', 100);
            $table->string('status', 30)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_approvals');
        Schema::dropIfExists('kyc_documents');
    }
};
