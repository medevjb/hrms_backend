<?php

namespace App\Models;

use App\Enums\HalfDayPeriod;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveStatus;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * §34–§41 leave request. `required_stages` is the approval chain this
 * specific request must pass, snapshotted at submission from the
 * requester's own role (§41) — later role changes never rewrite a
 * request already in flight. `current_stage` is whichever stage in that
 * list hasn't decided yet; null once the request reaches a terminal
 * status (HR_APPROVED/REJECTED/CANCELLED).
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_half_day
 * @property HalfDayPeriod|null $half_day_period
 * @property string $days_requested
 * @property string|null $reason
 * @property LeaveStatus $status
 * @property LeaveApprovalStage|null $current_stage
 * @property array<int, string> $required_stages
 * @property bool $is_direct_approval
 * @property string|null $direct_approval_reason
 * @property array<int, string>|null $bypassed_stages
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property string|null $rejection_reason
 * @property int|null $rejected_by_user_id
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by_user_id
 */
#[Fillable([
    'employee_id', 'leave_type_id', 'start_date', 'end_date', 'is_half_day', 'half_day_period',
    'days_requested', 'reason', 'status', 'current_stage', 'required_stages',
    'is_direct_approval', 'direct_approval_reason', 'bypassed_stages', 'submitted_at', 'decided_at',
    'rejection_reason', 'rejected_by_user_id', 'cancelled_at', 'cancelled_by_user_id',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'DRAFT',
        'is_half_day' => false,
        'is_direct_approval' => false,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_half_day' => 'boolean',
            'half_day_period' => HalfDayPeriod::class,
            'days_requested' => 'decimal:1',
            'status' => LeaveStatus::class,
            'current_stage' => LeaveApprovalStage::class,
            'required_stages' => 'array',
            'is_direct_approval' => 'boolean',
            'bypassed_stages' => 'array',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return HasMany<LeaveRequestApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }

    /**
     * @return HasMany<LeaveBalanceTransaction, $this>
     */
    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class);
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
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [LeaveStatus::HrApproved, LeaveStatus::Rejected, LeaveStatus::Cancelled], true);
    }

    public function isApproved(): bool
    {
        return $this->status === LeaveStatus::HrApproved;
    }
}
