<?php

namespace Database\Factories;

use App\Enums\HolidaySource;
use App\Enums\HolidayType;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->words(2, true),
            'date' => fake()->dateTimeBetween('now', '+6 months'),
            'type' => HolidayType::Company,
            'description' => fake()->sentence(),
        ];
    }

    /**
     * A holiday owned by the Google Bangladesh importer.
     */
    public function googleBd(): static
    {
        return $this->state(fn () => [
            'type' => HolidayType::National,
            'source' => HolidaySource::GoogleBd,
            'external_uid' => fake()->unique()->regexify('[0-9]{8}_[a-z0-9]{26}').'@google.com',
            'synced_at' => now(),
        ]);
    }
}
