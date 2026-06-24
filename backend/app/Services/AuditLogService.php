<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

final class AuditLogService
{
    public function log(
        string $action,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $payload = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'payload' => $payload,
            'ip_address' => $request?->ip(),
        ]);
    }
}
