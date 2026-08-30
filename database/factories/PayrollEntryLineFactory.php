<?php

namespace Database\Factories;

use App\Enums\PayrollLineType;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollEntryLine>
 */
class PayrollEntryLineFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_entry_id' => PayrollEntry::factory(),
            'category' => PayrollLineType::Basic->category(),
            'type' => PayrollLineType::Basic,
            'label' => 'Basic Salary',
            'amount' => '30000.0000',
            'is_manual' => false,
        ];
    }
}
