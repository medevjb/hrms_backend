<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\PersonalEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PersonalEvent>
 */
class PersonalEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::parse(fake()->dateTimeBetween('-1 month', '+2 months'))->startOfDay();

        return [
            'employee_id' => Employee::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->boolean() ? fake()->sentence() : null,
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(fake()->numberBetween(0, 4)),
        ];
    }
}
