<?php

use App\Enums\ScheduledTaskStatus;
use App\Models\ScheduledTaskRun;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskSkipped;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

/**
 * docs/PRD.md §79 — one `scheduled_task_runs` row per scheduled-command run,
 * recorded from the framework's scheduler events.
 */
function scheduledEvent(string $command = 'inspire'): Illuminate\Console\Scheduling\Event
{
    return app(Schedule::class)->command($command);
}

test('the factory persists each run status', function () {
    ScheduledTaskRun::factory()->succeeded()->create();
    ScheduledTaskRun::factory()->failed()->create();
    ScheduledTaskRun::factory()->skipped()->create();
    ScheduledTaskRun::factory()->running()->create();

    expect(ScheduledTaskRun::pluck('status')->map->value->sort()->values()->all())
        ->toBe(['failed', 'running', 'skipped', 'succeeded']);
});

test('a successful run records one succeeded row', function () {
    $event = scheduledEvent();
    Event::dispatch(new ScheduledTaskStarting($event));
    $event->exitCode = 0;
    Event::dispatch(new ScheduledTaskFinished($event, 0.42));

    $run = ScheduledTaskRun::query()->sole();
    expect($run->command)->toBe('inspire')
        ->and($run->status)->toBe(ScheduledTaskStatus::Succeeded)
        ->and($run->exit_code)->toBe(0)
        ->and($run->duration_ms)->toBe(420)
        ->and($run->finished_at)->not->toBeNull();
});

test('a non-zero exit code records a failed row', function () {
    $event = scheduledEvent();
    Event::dispatch(new ScheduledTaskStarting($event));
    $event->exitCode = 1;
    Event::dispatch(new ScheduledTaskFinished($event, 0.1));

    $run = ScheduledTaskRun::query()->sole();
    expect($run->status)->toBe(ScheduledTaskStatus::Failed)
        ->and($run->exit_code)->toBe(1);
});

test('a thrown exception records a failed row with the error as output', function () {
    $event = scheduledEvent();
    Event::dispatch(new ScheduledTaskStarting($event));
    Event::dispatch(new ScheduledTaskFailed($event, new RuntimeException('kaboom')));

    $run = ScheduledTaskRun::query()->sole();
    expect($run->status)->toBe(ScheduledTaskStatus::Failed)
        ->and($run->output)->toContain('kaboom');
});

test('a skipped run records one skipped row', function () {
    Event::dispatch(new ScheduledTaskSkipped(scheduledEvent()));

    expect(ScheduledTaskRun::query()->sole()->status)->toBe(ScheduledTaskStatus::Skipped);
});

test('the heartbeat command is not tracked', function () {
    $event = scheduledEvent('app:record-scheduler-heartbeat');
    Event::dispatch(new ScheduledTaskStarting($event));
    $event->exitCode = 0;
    Event::dispatch(new ScheduledTaskFinished($event, 0.01));

    expect(ScheduledTaskRun::query()->count())->toBe(0);
});

test('prune deletes old runs and marks abandoned running rows', function () {
    $old = ScheduledTaskRun::factory()->succeeded()->create();
    $old->forceFill(['created_at' => Carbon::now()->subDays(45)])->saveQuietly();

    $stale = ScheduledTaskRun::factory()->running()->create();
    $stale->forceFill(['started_at' => Carbon::now()->subHours(9)])->saveQuietly();

    $fresh = ScheduledTaskRun::factory()->succeeded()->create();

    $this->artisan('system:prune-schedule-runs')->assertSuccessful();

    expect(ScheduledTaskRun::query()->find($old->id))->toBeNull()
        ->and(ScheduledTaskRun::query()->find($stale->id)->status)->toBe(ScheduledTaskStatus::Unknown)
        ->and(ScheduledTaskRun::query()->find($fresh->id)->status)->toBe(ScheduledTaskStatus::Succeeded);
});

test('the prune command is scheduled daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'system:prune-schedule-runs'));

    expect($event)->not->toBeNull();
});
