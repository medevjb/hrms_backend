<?php

namespace App\Models;

use Database\Factories\EmployeeSalaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §59 — one effective-dated salary version. `ended_at` is null
 * for the current version and set to the day before the next version's
 * `effective_from` when superseded; history is never overwritten.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $effective_from
 * @property Carbon|null $ended_at
 * @property string $basic_salary
 * @property string $gross_monthly
 * @property string|null $note
 * @property int|null $created_by_user_id
 */
#[Fillable([
    'employee_id', 'effective_from', 'ended_at', 'basic_salary',
    'gross_monthly', 'note', 'created_by_user_id',
])]
class EmployeeSalary extends Model
{
    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'ended_at' => 'date',
            'basic_salary' => 'decimal:4',
            'gross_monthly' => 'decimal:4',
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
     * @return HasMany<EmployeeSalaryComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(EmployeeSalaryComponent::class);
    }
}
