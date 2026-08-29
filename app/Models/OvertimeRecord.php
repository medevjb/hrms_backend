<?php

namespace App\Models;

use App\Enums\OvertimeApprovalStage;
use App\Enums\OvertimeStatus;
use App\Enums\OvertimeType;
use Database\Factories\OvertimeRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * §42–§53 — one weekend/holiday overtime candidate, born from a single
 * attendance day (§52). `current_stage` is whichever stage in the fixed
 * §50 chain hasn't decided yet; null once the record reaches a terminal
 * status (APPROVED/REJECTED/PAYROLL_PROCESSED). `overtime_days` is what
 * detection computed (1 or 0); `manual_days_override` is HR's §68 grant on
 * top — effectiveOvertimeDays() is the one payroll (Phase 8) reads.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $attendance_record_id
 * @property Carbon $work_date
 * @property OvertimeType $type
 * @property int $worked_minutes
 * @property int $full_day_minutes_used
 * @property string $overtime_days
 * @property OvertimeStatus $status
 * @property OvertimeApprovalStage|null $current_stage
 * @property string|null $rejection_reason
 * @property int|null $rejected_by_user_id
 * @property string|null $manual_days_override
 * @property string|null $manual_adjustment_reason
 * @property int|null $adjusted_by_user_id
 * @property Carbon|null $adjusted_at
 * @property Carbon|null $decided_at
 * @property Carbon|null $payroll_processed_at
 */
#[Fillable([
    'employee_id', 'attendance_record_id', 'work_date', 'type', 'worked_minutes',
    'full_day_minutes_used', 'overtime_days', 'status', 'current_stage',
    'rejection_reason', 'rejected_by_user_id', 'manual_days_override',
    'manual_adjustment_reason', 'adjusted_by_user_id', 'adjusted_at', 'decided_at',
    'payroll_processed_at',
])]
class OvertimeRecord extends Model
{
    /** @use HasFactory<OvertimeRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'type' => OvertimeType::class,
            'overtime_days' => 'decimal:2',
            'manual_days_override' => 'decimal:2',
            'status' => OvertimeStatus::class,
            'current_stage' => OvertimeApprovalStage::class,
            'adjusted_at' => 'datetime',
            'decided_at' => 'datetime',
            'payroll_processed_at' => 'datetime',
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
     * @return BelongsTo<AttendanceRecord, $this>
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * @return HasMany<OvertimeApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(OvertimeApproval::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by_user_id');
    }

    /**
     * The day count payroll (§67, Phase 8) actually pays on: HR's §68
     * manual override wins whenever it's set, otherwise the value
     * detection computed against §46's threshold.
     */
    public function effectiveOvertimeDays(): float
    {
        return (float) ($this->manual_days_override ?? $this->overtime_days);
    }

    public function isApproved(): bool
    {
        return in_array($this->status, [OvertimeStatus::Approved, OvertimeStatus::PayrollProcessed], true);
    }

    public function isTerminal(): bool
    {
        return $this->current_stage === null;
    }
}
