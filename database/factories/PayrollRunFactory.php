<?php

namespace Database\Factories;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PayrollRun> */
class PayrollRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payroll_period_id' => PayrollPeriod::factory(),
            'sequence' => 1,
            'entry_count' => 0,
            'gross_total' => '0.0000',
            'deduction_total' => '0.0000',
            'net_total' => '0.0000',
        ];
    }
}
