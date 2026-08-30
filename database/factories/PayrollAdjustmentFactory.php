<?php

namespace Database\Factories;

use App\Enums\PayrollAdjustmentType;
use App\Models\PayrollAdjustment;
use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_entry_id' => PayrollEntry::factory(),
            'type' => PayrollAdjustmentType::Bonus,
            'label' => 'Performance bonus',
            'amount' => '2000.0000',
            'reason' => fake()->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }
}
