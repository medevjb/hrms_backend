<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payslip> */
class PayslipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'payroll_entry_id' => PayrollEntry::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'employee_id' => Employee::factory(),
            'reference' => 'PS-2026-08-'.fake()->unique()->numerify('####'),
            'gross_earnings' => '30000.0000',
            'total_deductions' => '0.0000',
            'net_salary' => '30000.0000',
            'file_path' => 'payslips/test.pdf',
            'generated_at' => now(),
        ];
    }
}
