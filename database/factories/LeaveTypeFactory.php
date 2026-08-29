<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Casual Leave', 'Sick Leave', 'Annual Leave', 'Unpaid Leave']);

        return [
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'annual_allocation_days' => 15,
            'is_paid' => true,
            'supports_half_day' => true,
            'carry_forward_enabled' => false,
            'accrual_mode' => 'UPFRONT',
            'is_active' => true,
        ];
    }
}
