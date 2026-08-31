<?php

namespace App\Listeners;

use App\Enums\ScheduledTaskStatus;
use App\Models\ScheduledTaskRun;
use App\Support\ScheduledCommandName;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * docs/PRD.md §79 — records one `scheduled_task_runs` row per execution of a
 * scheduled command by listening on the framework's own scheduler events, so
 * every task (including ones added later) is tracked with no per-command
 * boilerplate.
 */
class ScheduledTaskRunSubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(ScheduledTaskStarting::class, [self::class, 'recordStarting']);
        $events->listen(ScheduledTaskFinished::class, [self::class, 'recordFinished']);
        $events->listen(ScheduledTaskFailed::class, [self::class, 'recordFailed']);
        $events->listen(ScheduledTaskSkipped::class, [self::class, 'recordSkipped']);
    }

    public function recordStarting(ScheduledTaskStarting $event): void
    {
        if ($this->isUntracked($event->task)) {
            return;
        }

        ScheduledTaskRun::query()->create([
            'command' => ScheduledCommandName::for($event->task),
            'status' => ScheduledTaskStatus::Running,
            'started_at' => Carbon::now(),
        ]);
    }

    public function recordFinished(ScheduledTaskFinished $event): void
    {
        if ($this->isUntracked($event->task)) {
            return;
        }

        $exitCode = $event->task->exitCode;

        $this->close($event->task, [
            'status' => ($exitCode === null || $exitCode === 0)
                ? ScheduledTaskStatus::Succeeded
                : ScheduledTaskStatus::Failed,
            'duration_ms' => (int) round($event->runtime * 1000),
            'exit_code' => $exitCode,
            'output' => $this->outputTail($event->task),
        ]);
    }

    public function recordFailed(ScheduledTaskFailed $event): void
    {
        if ($this->isUntracked($event->task)) {
            return;
        }

        $run = $this->openRunFor($event->task);

        $this->close($event->task, [
            'status' => ScheduledTaskStatus::Failed,
            'duration_ms' => $run !== null ? (int) round(abs($run->started_at->diffInMilliseconds(Carbon::now()))) : null,
            'exit_code' => $event->task->exitCode,
            'output' => $this->tail((string) $event->exception, config('system-console.schedule.output_tail_bytes')),
        ], $run);
    }

    public function recordSkipped(ScheduledTaskSkipped $event): void
    {
        if ($this->isUntracked($event->task)) {
            return;
        }

        // A skipped task never fires `starting`, so there is no open row to close.
        ScheduledTaskRun::query()->create([
            'command' => ScheduledCommandName::for($event->task),
            'status' => ScheduledTaskStatus::Skipped,
            'started_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function close(Event $task, array $attributes, ?ScheduledTaskRun $run = null): void
    {
        $run ??= $this->openRunFor($task);

        if ($run === null) {
            $attributes['command'] = ScheduledCommandName::for($task);
            $attributes['started_at'] = Carbon::now();
            $attributes['finished_at'] = Carbon::now();
            ScheduledTaskRun::query()->create($attributes);

            return;
        }

        $run->update($attributes + ['finished_at' => Carbon::now()]);
    }

    private function isUntracked(Event $task): bool
    {
        /** @var list<string> $untracked */
        $untracked = (array) config('system-console.schedule.untracked_commands', []);

        return in_array(ScheduledCommandName::for($task), $untracked, true);
    }

    private function openRunFor(Event $task): ?ScheduledTaskRun
    {
        return ScheduledTaskRun::query()
            ->forCommand(ScheduledCommandName::for($task))
            ->where('status', ScheduledTaskStatus::Running)
            ->latest('started_at')
            ->first();
    }

    private function outputTail(Event $task): ?string
    {
        $path = $task->output;

        if ($path === '' || Str::contains($path, ['/dev/null', 'NUL']) || ! is_file($path)) {
            return null;
        }

        return $this->tail((string) file_get_contents($path), config('system-console.schedule.output_tail_bytes'));
    }

    private function tail(string $text, int $bytes): ?string
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        return strlen($text) > $bytes ? substr($text, -$bytes) : $text;
    }
}
