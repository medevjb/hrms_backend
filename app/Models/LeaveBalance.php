<?php

namespace App\Models;

use Database\Factories\LeaveBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One row per employee/leave-type/leave-year (docs/PRD.md §144). `balance`
 * is a cached sum kept in step by LeaveBalanceService — it must always be
 * reconstructible by replaying `transactions` in order.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $leave_year
 * @property string $balance
 */
#[Fillable(['employee_id', 'leave_type_id', 'leave_year', 'balance'])]
class LeaveBalance extends Model
{
    /** @use HasFactory<LeaveBalanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
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
     * @return HasMany<LeaveBalanceTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveBalanceTransaction::class);
    }
}
