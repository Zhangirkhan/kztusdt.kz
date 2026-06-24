<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Composite indexes for the hot money-path queries (network-scoped indexer and
 * sweep scans, withdrawal tx lookups, admin audit-log filters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            // Indexer filters: where network + (status in ...).
            $table->index(['network', 'status'], 'deposits_network_status_index');
        });

        Schema::table('sweeps', function (Blueprint $table): void {
            // Sweep pass: where network + (status in ...) order by id.
            $table->index(['network', 'status'], 'sweeps_network_status_index');
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            // Confirmation / reconciliation lookups by on-chain tx hash.
            $table->index('tx_hash', 'withdrawals_tx_hash_index');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_action_created_index');
            $table->dropIndex('audit_logs_entity_index');
        });

        Schema::table('withdrawals', function (Blueprint $table): void {
            $table->dropIndex('withdrawals_tx_hash_index');
        });

        Schema::table('sweeps', function (Blueprint $table): void {
            $table->dropIndex('sweeps_network_status_index');
        });

        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropIndex('deposits_network_status_index');
        });
    }
};
