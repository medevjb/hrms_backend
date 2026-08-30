<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => AnnouncementType::General,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
            'audience_type' => AnnouncementAudienceType::All,
            'status' => AnnouncementStatus::Draft,
            'acknowledgement_required' => false,
            'created_by_user_id' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => AnnouncementStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function ofType(AnnouncementType $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'acknowledgement_required' => $type->defaultsToAcknowledgement(),
        ]);
    }
}
