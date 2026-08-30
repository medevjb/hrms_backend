<?php

namespace Database\Factories;

use App\Enums\HolidayNoticeStatus;
use App\Models\Holiday;
use App\Models\HolidayNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HolidayNotice>
 */
class HolidayNoticeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holiday_id' => Holiday::factory(),
            'reference' => 'HN-'.fake()->year().'-'.fake()->unique()->numerify('####'),
            'status' => HolidayNoticeStatus::PendingApproval,
            'title' => 'Office closure notice — '.fake()->sentence(3),
            'message' => fake()->paragraph(),
            'return_date' => now()->addDays(6)->toDateString(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => HolidayNoticeStatus::Published,
            'signatory_name' => fake()->name(),
            'generated_at' => now(),
            'file_path' => 'holiday-notices/test.pdf',
        ]);
    }
}
