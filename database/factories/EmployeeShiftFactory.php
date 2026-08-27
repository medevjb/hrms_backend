<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeShift>
 */
class EmployeeShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shift_id' => Shift::factory(),
            'started_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'ended_at' => null,
        ];
    }
}
