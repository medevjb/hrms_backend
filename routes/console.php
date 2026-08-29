<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:record-scheduler-heartbeat')->everyMinute();

// docs/PRD.md §137 — closes yesterday's attendance daily, well after any
// shift (including an overnight one plus its check-in window) could still
// be open. The app's server timezone (config('app.timezone'), UTC) is
// assumed close enough to organization_settings.timezone for V1's single-
// office deployment; revisit if that assumption breaks (§131 multi-office).
Schedule::command('attendance:close')->dailyAt('02:00');

// docs/PRD.md §144 — checks daily whether today is the organization's
// leave-year start date and, if so, carries forward/expires balances and
// opens the new year. A no-op every other day.
Schedule::command('leave:rollover')->dailyAt('01:00');
