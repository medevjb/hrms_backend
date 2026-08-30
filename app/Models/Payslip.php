<?php

namespace App\Models;

use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §71 — the generated payslip document. Totals are snapshotted
 * so the payslip is fixed even if a later arrear touches the entry.
 *
 * @property int $id
 * @property int $payroll_entry_id
 * @property int $payroll_period_id
 * @property int $employee_id
 * @property string $reference
 * @property string $gross_earnings
 * @property string $total_deductions
 * @property string $net_salary
 * @property string $file_path
 * @property Carbon $generated_at
 */
#[Fillable([
    'payroll_entry_id', 'payroll_period_id', 'employee_id', 'reference',
    'gross_earnings', 'total_deductions', 'net_salary', 'file_path', 'generated_at',
])]
class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'gross_earnings' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'net_salary' => 'decimal:4',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(PayrollEntry::class, 'payroll_entry_id');
    }
}
