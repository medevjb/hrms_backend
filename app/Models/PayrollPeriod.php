<?php

namespace App\Models;

use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryDayCalculationMethod;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §63/§64 — one payroll period. The cutoff day and salary-day
 * method are snapshotted at creation so a later settings change never
 * rewrites a historical period (§64).
 *
 * @property int $id
 * @property string $label
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property PayrollPeriodStatus $status
 * @property int|null $cutoff_day_used
 * @property SalaryDayCalculationMethod $salary_day_calculation_method_used
 * @property Carbon|null $processed_at
 * @property Carbon|null $finalized_at
 * @property int|null $created_by_user_id
 */
#[Fillable([
    'label', 'start_date', 'end_date', 'status', 'cutoff_day_used',
    'salary_day_calculation_method_used', 'processed_at', 'finalized_at', 'created_by_user_id',
])]
class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    protected $attributes = ['status' => PayrollPeriodStatus::Upcoming->value];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => PayrollPeriodStatus::class,
            'salary_day_calculation_method_used' => SalaryDayCalculationMethod::class,
            'processed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayrollEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(PayrollEntry::class);
    }
}
