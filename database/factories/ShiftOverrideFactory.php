<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftOverride>
 */
class ShiftOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shift_id' => Shift::factory(),
            'work_date' => fake()->dateTimeBetween('now', '+1 month'),
            'reason' => fake()->sentence(),
        ];
    }
}
