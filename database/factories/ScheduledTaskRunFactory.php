<?php

namespace Database\Factories;

use App\Enums\ScheduledTaskStatus;
use App\Models\ScheduledTaskRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/** @extends Factory<ScheduledTaskRun> */
class ScheduledTaskRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $startedAt = Carbon::instance(fake()->dateTimeBetween('-7 days', 'now'));
        $durationMs = fake()->numberBetween(20, 5000);

        return [
            'command' => fake()->randomElement([
                'attendance:close', 'leave:rollover', 'holidays:scan-notices', 'announcements:publish-due',
            ]),
            'status' => ScheduledTaskStatus::Succeeded,
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->addMilliseconds($durationMs),
            'duration_ms' => $durationMs,
            'exit_code' => 0,
            'output' => fake()->optional()->sentence(),
        ];
    }

    public function succeeded(): static
    {
        return $this->state(['status' => ScheduledTaskStatus::Succeeded, 'exit_code' => 0]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => ScheduledTaskStatus::Failed,
            'exit_code' => 1,
            'output' => "Exception: something broke\n#0 /app/foo.php(12)",
        ]);
    }

    public function skipped(): static
    {
        return $this->state([
            'status' => ScheduledTaskStatus::Skipped,
            'finished_at' => null,
            'duration_ms' => null,
            'exit_code' => null,
        ]);
    }

    public function running(): static
    {
        return $this->state([
            'status' => ScheduledTaskStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'duration_ms' => null,
            'exit_code' => null,
        ]);
    }
}
