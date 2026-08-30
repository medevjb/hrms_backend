<?php

namespace Database\Factories;

use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryDayCalculationMethod;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A distinct month per factory instance so the unique label /
        // (start_date, end_date) constraints don't collide across a test.
        $monthsBack = fake()->unique()->numberBetween(1, 240);
        $start = now()->subMonthsNoOverflow($monthsBack)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return [
            'label' => $start->format('F Y'),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => PayrollPeriodStatus::Open,
            'cutoff_day_used' => null,
            'salary_day_calculation_method_used' => SalaryDayCalculationMethod::Fixed30Days,
        ];
    }

    public function status(PayrollPeriodStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
