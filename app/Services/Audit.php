<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class Audit
{
    public static function log(string $action, string $entity, string|int $entityId, array $data = []): void
    {
        AuditLog::create([
            'actor_id' => Auth::id(),
            'action' => $action,
            'entity' => $entity,
            'entity_id' => (string) $entityId,
            'data' => $data ?: null,
        ]);
    }
}
