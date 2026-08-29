<?php

namespace App\Console\Commands;

use App\Models\OrganizationSettings;
use App\Services\LeaveBalanceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §144 — carries forward or expires every employee's unused
 * balance and opens the new leave year. Scheduled to run daily; it only
 * does anything on the organization's actual leave-year start date, and
 * every transaction it writes is idempotent by note (see
 * LeaveBalanceService), so a re-run or a missed/duplicated cron tick never
 * double-applies.
 */
#[Signature('leave:rollover {year? : The leave year being opened, YYYY, defaults to today\'s}')]
#[Description('Carry forward or expire unused leave balances and open the new leave year')]
class LeaveYearRolloverCommand extends Command
{
    public function handle(LeaveBalanceService $balances): int
    {
        $settings = OrganizationSettings::current();
        $today = Carbon::now($settings->timezone);
        $startMonth = $settings->leave_year_start_month;

        $newLeaveYear = $this->argument('year')
            ? (int) $this->argument('year')
            : $balances->leaveYearFor($today, $startMonth);

        if (! $this->argument('year') && ! ($today->day === 1 && $today->month === $startMonth)) {
            $this->components->info("Today ({$today->toDateString()}) is not the leave-year start date — nothing to do.");

            return self::SUCCESS;
        }

        $balances->runYearRollover($newLeaveYear);

        $this->components->info("Rolled leave balances into leave year {$newLeaveYear}.");

        return self::SUCCESS;
    }
}
