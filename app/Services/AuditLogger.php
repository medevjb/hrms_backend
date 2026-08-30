<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * docs/PRD.md §83 — writes an audit_logs row for a sensitive action. Call
 * it inside the same transaction as the change it records; a rolled-back
 * transaction takes the audit row with it.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $oldData
     * @param  array<string, mixed>|null  $newData
     */
    public function record(
        AuditAction $action,
        ?Model $entity = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        $user = $actor ?? Auth::user();

        return AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'action' => $action,
            'entity_type' => $entity !== null ? $entity::class : null,
            'entity_id' => $entity?->getKey(),
            'old_data' => $oldData,
            'new_data' => $newData,
            'reason' => $reason,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255) ?: null,
        ]);
    }
}
