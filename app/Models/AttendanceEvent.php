<?php

namespace App\Models;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSource;
use Database\Factories\AttendanceEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A raw punch (docs/PRD.md §24) — append-only; AttendanceRecord is what
 * gets summarized from these, never the other way around.
 *
 * @property int $id
 * @property int $employee_id
 * @property AttendanceEventType $event_type
 * @property Carbon $event_time
 * @property AttendanceSource $source
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['employee_id', 'event_type', 'event_time', 'source', 'created_by', 'metadata'])]
class AttendanceEvent extends Model
{
    /** @use HasFactory<AttendanceEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_type' => AttendanceEventType::class,
            'event_time' => 'datetime',
            'source' => AttendanceSource::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
