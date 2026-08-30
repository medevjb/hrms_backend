<?php

namespace App\Models;

use Database\Factories\EmployeeSalaryComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * docs/PRD.md §59 — the amount of one component within one salary version.
 *
 * @property int $id
 * @property int $employee_salary_id
 * @property int $salary_component_id
 * @property string $amount
 */
#[Fillable(['employee_salary_id', 'salary_component_id', 'amount'])]
class EmployeeSalaryComponent extends Model
{
    /** @use HasFactory<EmployeeSalaryComponentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<EmployeeSalary, $this>
     */
    public function salary(): BelongsTo
    {
        return $this->belongsTo(EmployeeSalary::class, 'employee_salary_id');
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
