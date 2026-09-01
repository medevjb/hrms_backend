<?php

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

test('the ping command queues a heartbeat job', function () {
    Bus::fake();

    $this->artisan('app:ping-queue-worker')->assertSuccessful();

    Bus::assertDispatched(RecordQueueHeartbeat::class);
});

test('the heartbeat job records the current time when a worker runs it', function () {
    (new RecordQueueHeartbeat)->handle();

    expect(Cache::get('queue:worker-heartbeat'))->not->toBeNull();
});

test('the ping command is scheduled every minute and is not tracked', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'app:ping-queue-worker'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('* * * * *');
    expect(config('system-console.schedule.untracked_commands'))
        ->toContain('app:ping-queue-worker');
});
