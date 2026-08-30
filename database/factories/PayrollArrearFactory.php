<?php

namespace Database\Factories;

use App\Enums\PayrollArrearSourceType;
use App\Enums\PayrollArrearStatus;
use App\Models\Employee;
use App\Models\PayrollArrear;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollArrear> */
class PayrollArrearFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'source_type' => PayrollArrearSourceType::Overtime,
            'original_period_id' => PayrollPeriod::factory(),
            'amount' => '1000.0000',
            'reason' => fake()->sentence(),
            'status' => PayrollArrearStatus::Pending,
        ];
    }
}
