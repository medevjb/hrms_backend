<?php

namespace App\Models;

use App\Enums\PayrollAcknowledgementStatus;
use App\Enums\PayrollDisputeStatus;
use App\Enums\PayrollEntryStatus;
use Database\Factories\PayrollEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §66 — one employee's payroll for one period. The count and
 * daily_salary columns are the inputs the lines were computed from (§141),
 * kept so a dispute can be recomputed rather than argued.
 *
 * @property int $id
 * @property int $payroll_period_id
 * @property int $employee_id
 * @property int|null $employee_salary_id
 * @property PayrollEntryStatus $status
 * @property PayrollAcknowledgementStatus $acknowledgement_status
 * @property Carbon|null $released_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $finalized_at
 * @property string $basic_salary
 * @property string $daily_salary
 * @property int $period_days
 * @property string $late_days
 * @property string $absent_days
 * @property string $unpaid_leave_days
 * @property string $overtime_days
 * @property string $gross_earnings
 * @property string $total_deductions
 * @property string $net_salary
 * @property Carbon|null $calculated_at
 */
#[Fillable([
    'payroll_period_id', 'employee_id', 'employee_salary_id', 'status', 'acknowledgement_status',
    'basic_salary', 'daily_salary', 'period_days', 'late_days', 'absent_days',
    'unpaid_leave_days', 'overtime_days', 'gross_earnings', 'total_deductions',
    'net_salary', 'calculated_at', 'released_at', 'acknowledged_at', 'finalized_at',
])]
class PayrollEntry extends Model
{
    /** @use HasFactory<PayrollEntryFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => PayrollEntryStatus::Draft->value,
        'acknowledgement_status' => PayrollAcknowledgementStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollEntryStatus::class,
            'acknowledgement_status' => PayrollAcknowledgementStatus::class,
            'released_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'finalized_at' => 'datetime',
            'basic_salary' => 'decimal:4',
            'daily_salary' => 'decimal:4',
            'period_days' => 'integer',
            'late_days' => 'decimal:2',
            'absent_days' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'overtime_days' => 'decimal:2',
            'gross_earnings' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'net_salary' => 'decimal:4',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return HasMany<PayrollEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollEntryLine::class);
    }

    /**
     * @return HasMany<PayrollAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class);
    }

    /**
     * @return HasMany<PayrollDispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(PayrollDispute::class);
    }

    /**
     * @return HasOne<Payslip, $this>
     */
    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    public function hasOpenDispute(): bool
    {
        return $this->disputes()->where('status', PayrollDisputeStatus::Open)->exists();
    }
}
