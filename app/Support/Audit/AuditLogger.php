<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request)
    {
    }

    public function log(
        string $action,
        Model|string $entity,
        int|string|null $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        $entityType = $entity instanceof Model ? $entity::class : $entity;
        $resolvedEntityId = $entity instanceof Model ? $entity->getKey() : $entityId;

        return AuditLog::query()->create([
            'user_id' => $this->request->user()?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => is_numeric($resolvedEntityId) ? (int) $resolvedEntityId : null,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 2000),
            'created_at' => now(),
        ]);
    }
}
