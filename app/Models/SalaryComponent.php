<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * docs/PRD.md §59 — a salary component definition (Basic Salary, Housing
 * Allowance, ...). The amounts live per-employee on
 * employee_salary_components; this is just the catalogue.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property SalaryComponentType $type
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'type', 'sort_order', 'is_active'])]
class SalaryComponent extends Model
{
    /** @use HasFactory<SalaryComponentFactory> */
    use HasFactory;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'is_active' => 'boolean',
        ];
    }
}
