<?php

namespace Database\Factories;

use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryComponent>
 */
class SalaryComponentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('COMP????')),
            'name' => ucfirst(fake()->word()).' Allowance',
            'type' => SalaryComponentType::Allowance,
            'sort_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    public function basic(): static
    {
        return $this->state(fn () => [
            'code' => 'BASIC',
            'name' => 'Basic Salary',
            'type' => SalaryComponentType::Basic,
            'sort_order' => 0,
        ]);
    }
}
