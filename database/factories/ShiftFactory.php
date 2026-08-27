<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Standard Shift',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'expected_work_minutes' => 480,
            'break_minutes' => 60,
            'late_grace_minutes' => null,
        ];
    }

    public function overnight(): static
    {
        return $this->state([
            'name' => 'Night Shift',
            'start_time' => '20:00:00',
            'end_time' => '05:00:00',
            'expected_work_minutes' => 480,
        ]);
    }
}
