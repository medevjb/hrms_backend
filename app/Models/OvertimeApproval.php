<?php

namespace App\Models;

use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeApprovalStage;
use Database\Factories\OvertimeApprovalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per stage decision (docs/PRD.md §50) — the audit trail an
 * overtime record's status alone can't carry, especially for §50's
 * "exceptional authority" approvals where an Admin/Head HR collapses the
 * remaining chain in a single act.
 *
 * @property int $id
 * @property int $overtime_record_id
 * @property OvertimeApprovalStage $stage
 * @property int $approver_user_id
 * @property OvertimeApprovalDecision $decision
 * @property string|null $reason
 * @property Carbon $decided_at
 */
#[Fillable(['overtime_record_id', 'stage', 'approver_user_id', 'decision', 'reason', 'decided_at'])]
class OvertimeApproval extends Model
{
    /** @use HasFactory<OvertimeApprovalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage' => OvertimeApprovalStage::class,
            'decision' => OvertimeApprovalDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OvertimeRecord, $this>
     */
    public function overtimeRecord(): BelongsTo
    {
        return $this->belongsTo(OvertimeRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
