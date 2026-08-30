<?php

namespace Database\Factories;

use App\Enums\PayrollDisputeStatus;
use App\Models\PayrollDispute;
use App\Models\PayrollEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollDispute> */
class PayrollDisputeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payroll_entry_id' => PayrollEntry::factory(),
            'raised_by_user_id' => User::factory(),
            'reason' => fake()->sentence(),
            'status' => PayrollDisputeStatus::Open,
        ];
    }
}
