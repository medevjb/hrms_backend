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

// docs/PRD.md §55 — the five-day holiday reminder: drafts a notice and
// notifies Head HR for any active holiday exactly five days out. Idempotent
// per holiday, so running it every day (not just once per holiday) is safe.
Schedule::command('holidays:scan-notices')->dailyAt('06:00');

// Bangladesh national public holidays, pulled from Google's public
// "Holidays in Bangladesh" calendar. Weekly is plenty — the feed only
// moves when a religious date is confirmed or a new year is published.
// Upserts by the Google event UID and never touches a hand-added or
// admin-edited holiday (see App\Services\BangladeshHolidayImporter).
Schedule::command('holidays:import-bd')->weeklyOn(1, '04:00');

// docs/PRD.md §57 — releases announcements whose scheduled publish_at has
// arrived and expires ones past their expires_at. Hourly so a scheduled
// publish lands close to its intended time.
Schedule::command('announcements:publish-due')->hourly();

// docs/PRD.md §79 — keeps the scheduled-task run history bounded and
// reclassifies runs abandoned by a killed process.
Schedule::command('system:prune-schedule-runs')->dailyAt('03:30');
