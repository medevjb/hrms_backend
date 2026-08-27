<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;

test('the heartbeat command records the current time', function () {
    $this->artisan('app:record-scheduler-heartbeat')->assertSuccessful();

    expect(Cache::get('scheduler:heartbeat'))->not->toBeNull();
});

test('the heartbeat command is scheduled to run every minute', function () {
    $schedule = app(Schedule::class);

    $event = collect($schedule->events())
        ->first(fn ($event) => str_contains($event->command, 'app:record-scheduler-heartbeat'));

    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('* * * * *');
});
