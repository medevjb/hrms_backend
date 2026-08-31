<?php

namespace App\Services;

use App\Enums\ScheduledTaskStatus;
use App\Models\ScheduledTaskRun;
use App\Support\ScheduledCommandName;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §79 — pairs the live application scheduler (cron expression, next
 * due time) with the recorded run history for each command.
 */
class ScheduleInspector
{
    public function __construct(private readonly Schedule $schedule) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function commands(): array
    {
        $latestRuns = $this->latestRunPerCommand();
        $recentFailures = $this->recentFailureCounts();

        $commands = [];

        foreach ($this->schedule->events() as $event) {
            $name = ScheduledCommandName::for($event);
            $latest = $latestRuns[$name] ?? null;

            $commands[] = [
                'command' => $name,
                'expression' => $event->getExpression(),
                'description' => is_string($event->description) ? $event->description : null,
                'next_due_at' => $this->nextDue($event),
                'without_overlapping' => $event->withoutOverlapping,
                'last_run' => $latest !== null ? $this->run($latest) : null,
                'recent_failures' => $recentFailures[$name] ?? 0,
            ];
        }

        return $commands;
    }

    /**
     * @return array<string, mixed>
     */
    public function history(string $command, int $page = 1, int $perPage = 25): array
    {
        $total = ScheduledTaskRun::query()->forCommand($command)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        $runs = ScheduledTaskRun::query()
            ->forCommand($command)
            ->orderByDesc('started_at')
            ->forPage($page, $perPage)
            ->get()
            ->map($this->run(...));

        return [
            'command' => $command,
            'is_registered' => $this->isRegistered($command),
            'data' => $runs->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    public function isRegistered(string $command): bool
    {
        foreach ($this->schedule->events() as $event) {
            if (ScheduledCommandName::for($event) === $command) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function run(ScheduledTaskRun $run): array
    {
        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'started_at' => $run->started_at->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'duration_ms' => $run->duration_ms,
            'exit_code' => $run->exit_code,
            'output' => $run->output,
        ];
    }

    private function nextDue(Event $event): ?string
    {
        try {
            return Carbon::instance($event->nextRunDate())->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, ScheduledTaskRun>
     */
    private function latestRunPerCommand(): array
    {
        $latest = [];

        foreach (ScheduledTaskRun::query()->orderByDesc('started_at')->get() as $run) {
            $latest[$run->command] ??= $run;
        }

        return $latest;
    }

    /**
     * @return array<string, int>
     */
    private function recentFailureCounts(): array
    {
        return ScheduledTaskRun::query()
            ->where('status', ScheduledTaskStatus::Failed)
            ->where('started_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('command, count(*) as total')
            ->groupBy('command')
            ->pluck('total', 'command')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
