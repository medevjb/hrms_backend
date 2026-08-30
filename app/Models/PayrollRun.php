<?php

namespace App\Models;

use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/PRD.md §69 — an audit row per draft calculation of a period.
 *
 * @property int $id
 * @property int $payroll_period_id
 * @property int $sequence
 * @property int $entry_count
 * @property string $gross_total
 * @property string $deduction_total
 * @property string $net_total
 * @property int|null $triggered_by_user_id
 */
#[Fillable([
    'payroll_period_id', 'sequence', 'entry_count', 'gross_total',
    'deduction_total', 'net_total', 'triggered_by_user_id',
])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gross_total' => 'decimal:4',
            'deduction_total' => 'decimal:4',
            'net_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
