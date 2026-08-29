<?php

namespace Database\Factories;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceAdjustment>
 */
class AttendanceAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_record_id' => AttendanceRecord::factory(),
            'field' => 'check_in',
            'old_value' => null,
            'new_value' => null,
            'reason' => fake()->sentence(),
        ];
    }
}
