<?php

namespace App\Models;

use App\Enums\PayrollAdjustmentType;
use Database\Factories\PayrollAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/PRD.md §68 — a manual earning / deduction / bonus / penalty waiver
 * on a draft payroll entry, with the full "reason, previous value, new
 * value, changed by, changed at" audit §68 requires.
 *
 * @property int $id
 * @property int $payroll_entry_id
 * @property PayrollAdjustmentType $type
 * @property string $label
 * @property string $amount
 * @property string $reason
 * @property string|null $previous_value
 * @property string|null $new_value
 * @property int $created_by_user_id
 */
#[Fillable([
    'payroll_entry_id', 'type', 'label', 'amount', 'reason',
    'previous_value', 'new_value', 'created_by_user_id',
])]
class PayrollAdjustment extends Model
{
    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => PayrollAdjustmentType::class,
            'amount' => 'decimal:4',
            'previous_value' => 'decimal:4',
            'new_value' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PayrollEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
