<?php

namespace App\Console\Commands;

use App\Models\OrganizationSettings;
use App\Services\HolidayNoticeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §55 — the five-day holiday reminder. Scheduled once a day
 * (routes/console.php); drafts a notice and pings Head HR for any active
 * holiday exactly five days out, idempotent by holiday_reminders' unique
 * holiday_id so the five in-window days don't produce five reminders.
 */
#[Signature('holidays:scan-notices {date? : Scan date, YYYY-MM-DD, defaults to today}')]
#[Description('Draft holiday notices and notify Head HR for holidays five days away')]
class ScanHolidayNoticesCommand extends Command
{
    public function handle(HolidayNoticeService $notices): int
    {
        $settings = OrganizationSettings::current();

        $today = $this->argument('date')
            ? Carbon::parse($this->argument('date'), $settings->timezone)
            : Carbon::now($settings->timezone);

        $count = $notices->scanForUpcomingHolidays($today);

        $this->components->info(
            $count === 0
                ? "No holidays five days out from {$today->toDateString()}."
                : "Drafted {$count} holiday notice(s) and notified Head HR.",
        );

        return self::SUCCESS;
    }
}
