<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One summarized row per employee per work_date (docs/PRD.md §25).
 * shift_start_used/shift_end_used/grace_minutes_used are a point-in-time
 * snapshot of what ShiftService resolved at check-in (or nightly-close)
 * time — never re-derived from current settings later (§95, §22).
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property int|null $shift_id
 * @property Carbon|null $shift_start_used
 * @property Carbon|null $shift_end_used
 * @property int|null $grace_minutes_used
 * @property Carbon|null $check_in
 * @property Carbon|null $check_out
 * @property int|null $worked_minutes
 * @property int|null $late_minutes
 * @property AttendanceStatus $status
 * @property bool $is_manual_adjustment
 */
#[Fillable([
    'employee_id', 'work_date', 'shift_id', 'shift_start_used', 'shift_end_used',
    'grace_minutes_used', 'check_in', 'check_out', 'worked_minutes', 'late_minutes',
    'status', 'is_manual_adjustment',
])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    protected $attributes = ['is_manual_adjustment' => false];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'shift_start_used' => 'datetime',
            'shift_end_used' => 'datetime',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'status' => AttendanceStatus::class,
            'is_manual_adjustment' => 'boolean',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return HasMany<AttendanceAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(AttendanceAdjustment::class);
    }
}
