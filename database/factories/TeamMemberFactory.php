<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamMember>
 */
class TeamMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'employee_id' => Employee::factory(),
            'started_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_at' => fake()->dateTimeBetween($attributes['started_at'] ?? '-1 year', 'now'),
        ]);
    }
}
