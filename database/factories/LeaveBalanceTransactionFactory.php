<?php

namespace Database\Factories;

use App\Enums\LeaveBalanceTransactionType;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveBalanceTransaction>
 */
class LeaveBalanceTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_balance_id' => LeaveBalance::factory(),
            'type' => LeaveBalanceTransactionType::Accrual,
            'amount' => 15,
        ];
    }
}
