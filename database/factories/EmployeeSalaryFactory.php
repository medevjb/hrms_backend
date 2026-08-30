<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basic = fake()->numberBetween(20000, 60000);

        return [
            'employee_id' => Employee::factory(),
            'effective_from' => now()->subMonths(6)->toDateString(),
            'ended_at' => null,
            'basic_salary' => number_format($basic, 4, '.', ''),
            'gross_monthly' => number_format($basic, 4, '.', ''),
            'note' => null,
        ];
    }
}
