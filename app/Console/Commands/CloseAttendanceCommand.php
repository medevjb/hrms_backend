<?php

namespace App\Console\Commands;

use App\Enums\PermissionName;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Notifications\AttendanceCloseSummary;
use App\Services\AttendanceService;
use App\Services\OvertimeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * docs/PRD.md §137 — closes a work_date's attendance: produces
 * ABSENT/MISSING_CHECKOUT/HALF_DAY/WEEKEND/HOLIDAY records for every
 * active employee, since a check-in never creates these on its own.
 *
 * Runs against yesterday (organization timezone) by default — "after the
 * last shift of the target date has ended" in practice means giving every
 * overnight shift's generous check-in window room to close naturally
 * before the job asks whether a check-in ever happened.
 */
#[Signature('attendance:close {date? : Work date to close, YYYY-MM-DD, defaults to yesterday}')]
#[Description("Close a work date's attendance: produce ABSENT/MISSING_CHECKOUT/HALF_DAY/WEEKEND/HOLIDAY records")]
class CloseAttendanceCommand extends Command
{
    public function handle(AttendanceService $attendance, OvertimeService $overtime): int
    {
        $settings = OrganizationSettings::current();

        $workDate = $this->argument('date')
            ? Carbon::parse($this->argument('date'), $settings->timezone)
            : Carbon::now($settings->timezone)->subDay();

        $summary = $attendance->closeWorkDate($workDate);

        // §52 — overtime detection reads the just-finalised attendance
        // (WEEKEND/HOLIDAY status, worked_minutes) for the same date.
        $overtimeSummary = $overtime->detectForWorkDate($workDate);

        $recipients = User::query()->get()->filter(
            fn (User $user) => $user->hasPermission(PermissionName::AttendanceManage),
        );

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new AttendanceCloseSummary($summary));
        }

        $this->components->info(
            "Closed {$workDate->toDateString()}: {$summary->absent} absent, ".
            "{$summary->missingCheckout} missing checkout, {$summary->halfDay} half day, ".
            "{$summary->weekend} weekend, {$summary->holiday} holiday, {$summary->onLeave} on leave, ".
            "{$summary->unchanged} unchanged, {$summary->skippedManualAdjustment} skipped (manual adjustment).",
        );

        $this->components->info(
            "Overtime {$workDate->toDateString()}: {$overtimeSummary->detected} detected, ".
            "{$overtimeSummary->rejectedInsufficientDuration} rejected (insufficient duration).",
        );

        return self::SUCCESS;
    }
}
