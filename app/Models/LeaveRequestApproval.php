<?php

namespace App\Models;

use App\Enums\LeaveApprovalDecision;
use App\Enums\LeaveApprovalStage;
use Database\Factories\LeaveRequestApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per stage decision (docs/PRD.md §38, §40) — the audit trail a
 * leave request's status alone can't carry, since §40's direct approval
 * needs "reason, bypassed stages, approving person, timestamp" preserved.
 *
 * @property int $id
 * @property int $leave_request_id
 * @property LeaveApprovalStage $stage
 * @property int $approver_user_id
 * @property LeaveApprovalDecision $decision
 * @property string|null $reason
 * @property Carbon $decided_at
 */
#[Fillable(['leave_request_id', 'stage', 'approver_user_id', 'decision', 'reason', 'decided_at'])]
class LeaveRequestApproval extends Model
{
    /** @use HasFactory<LeaveRequestApprovalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage' => LeaveApprovalStage::class,
            'decision' => LeaveApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
