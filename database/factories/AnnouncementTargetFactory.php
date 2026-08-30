<?php

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementTarget>
 */
class AnnouncementTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'target_type' => AnnouncementTargetType::Team,
            'target_id' => Team::factory(),
        ];
    }
}
