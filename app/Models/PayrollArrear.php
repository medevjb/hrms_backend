<?php

namespace App\Models;

use App\Enums\PayrollArrearSourceType;
use App\Enums\PayrollArrearStatus;
use App\Support\Money;
use Database\Factories\PayrollArrearFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §72/§146 — money carried from a closed period into the next
 * open one. Positive is owed to the employee (late-approved overtime);
 * negative is a recovery.
 *
 * @property int $id
 * @property int $employee_id
 * @property PayrollArrearSourceType $source_type
 * @property int|null $source_id
 * @property int $original_period_id
 * @property int|null $target_period_id
 * @property string $amount
 * @property string $reason
 * @property PayrollArrearStatus $status
 * @property int|null $created_by_user_id
 * @property Carbon|null $applied_at
 */
#[Fillable([
    'employee_id', 'source_type', 'source_id', 'original_period_id', 'target_period_id',
    'amount', 'reason', 'status', 'created_by_user_id', 'applied_at',
])]
class PayrollArrear extends Model
{
    /** @use HasFactory<PayrollArrearFactory> */
    use HasFactory;

    protected $attributes = ['status' => PayrollArrearStatus::Pending->value];

    protected function casts(): array
    {
        return [
            'source_type' => PayrollArrearSourceType::class,
            'status' => PayrollArrearStatus::class,
            'amount' => 'decimal:4',
            'applied_at' => 'datetime',
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
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function originalPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'original_period_id');
    }

    public function isRecovery(): bool
    {
        return Money::isNegative($this->amount);
    }
}
