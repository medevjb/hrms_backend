<?php

namespace App\Models;

use App\Enums\LeaveBalanceTransactionType;
use Database\Factories\LeaveBalanceTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One immutable row per balance movement (docs/PRD.md §144) — never
 * updated or deleted, only appended.
 *
 * @property int $id
 * @property int $leave_balance_id
 * @property LeaveBalanceTransactionType $type
 * @property string $amount
 * @property int|null $leave_request_id
 * @property string|null $note
 * @property int|null $created_by_user_id
 */
#[Fillable(['leave_balance_id', 'type', 'amount', 'leave_request_id', 'note', 'created_by_user_id'])]
class LeaveBalanceTransaction extends Model
{
    /** @use HasFactory<LeaveBalanceTransactionFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => LeaveBalanceTransactionType::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<LeaveBalance, $this>
     */
    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
