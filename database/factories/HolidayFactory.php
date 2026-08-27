<?php

namespace Database\Factories;

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
}
