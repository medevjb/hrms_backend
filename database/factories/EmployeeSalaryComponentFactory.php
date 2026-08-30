<?php

namespace Database\Factories;

use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalaryComponent>
 */
class EmployeeSalaryComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_salary_id' => EmployeeSalary::factory(),
            'salary_component_id' => SalaryComponent::factory()->basic(),
            'amount' => number_format(fake()->numberBetween(10000, 50000), 4, '.', ''),
        ];
    }
}
