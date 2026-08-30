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
        $month = fake()->dateTimeBetween('-6 months', 'now');
        $start = (clone $month)->modify('first day of this month');
        $end = (clone $month)->modify('last day of this month');

        return [
            'label' => $start->format('F Y'),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
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
