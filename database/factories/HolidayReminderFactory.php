<?php

namespace Database\Factories;

use App\Enums\HolidayReminderStatus;
use App\Models\Holiday;
use App\Models\HolidayReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HolidayReminder>
 */
class HolidayReminderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holiday_id' => Holiday::factory(),
            'lead_days_used' => 5,
            'triggered_on' => now()->toDateString(),
            'status' => HolidayReminderStatus::Pending,
            'head_hr_notified_at' => now(),
        ];
    }

    public function actioned(): static
    {
        return $this->state(fn () => [
            'status' => HolidayReminderStatus::Actioned,
            'actioned_at' => now(),
        ]);
    }
}
