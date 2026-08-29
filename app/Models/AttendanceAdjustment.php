<?php

namespace App\Models;

use Database\Factories\AttendanceAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per field changed by a manual correction (docs/PRD.md §32) — old
 * value, new value, reason, who, when. The AttendanceRecord itself is
 * updated in place and flagged is_manual_adjustment=true; this table is
 * the append-only history of why.
 *
 * @property int $id
 * @property int $attendance_record_id
 * @property string $field
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string $reason
 * @property int|null $changed_by
 */
#[Fillable(['attendance_record_id', 'field', 'old_value', 'new_value', 'reason', 'changed_by'])]
class AttendanceAdjustment extends Model
{
    /** @use HasFactory<AttendanceAdjustmentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<AttendanceRecord, $this>
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
