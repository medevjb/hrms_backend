<?php

namespace Database\Factories;

use App\Enums\LatePenaltyDeductionMode;
use App\Enums\LatePenaltyOutcome;
use App\Models\LatePenaltyRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LatePenaltyRule>
 */
class LatePenaltyRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'effective_from' => now()->subYear()->toDateString(),
            'late_days_threshold' => 5,
            'outcome' => LatePenaltyOutcome::Deduction,
            'deduction_mode' => LatePenaltyDeductionMode::DayFraction,
            'deduction_value' => '0.5000',
        ];
    }

    public function warning(int $threshold = 3): static
    {
        return $this->state(fn () => [
            'late_days_threshold' => $threshold,
            'outcome' => LatePenaltyOutcome::Warning,
            'deduction_mode' => null,
            'deduction_value' => null,
        ]);
    }
}
