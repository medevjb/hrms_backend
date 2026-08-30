<?php

namespace App\Models;

use App\Enums\AuditAction;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * docs/PRD.md §83 — one audited action. Append-only: the model refuses to
 * be updated or deleted, so the "no endpoint and no permission" rule is
 * also enforced at the ORM layer.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditAction $action
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property array<string, mixed>|null $old_data
 * @property array<string, mixed>|null $new_data
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[Fillable([
    'user_id', 'action', 'entity_type', 'entity_id', 'old_data', 'new_data',
    'reason', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'old_data' => 'array',
            'new_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Audit log rows are append-only (docs/PRD.md §83).'));
        static::deleting(fn () => throw new RuntimeException('Audit log rows are append-only (docs/PRD.md §83).'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
