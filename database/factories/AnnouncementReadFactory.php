<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementRead>
 */
class AnnouncementReadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'employee_id' => Employee::factory(),
            'acknowledged' => false,
            'read_at' => now(),
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn () => ['acknowledged' => true]);
    }
}
