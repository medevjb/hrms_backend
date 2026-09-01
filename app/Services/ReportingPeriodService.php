<?php

namespace App\Services;

use App\Models\OrganizationSettings;
use App\Support\ReportingPeriod;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resolves dates to the organization's reporting month (docs/PRD.md §85).
 * The one place month boundaries are computed — attendance, dashboard,
 * reports, leave, and payroll all route through here so a single admin
 * setting (`reporting_month_cutoff_day`) shifts every "this month" in the
 * product at once. A null cutoff means calendar months, i.e. the
 * behaviour before the setting existed.
 */
class ReportingPeriodService
{
    /** Highest cutoff the setting allows — every month has at least this many days. */
    private const MAX_CUTOFF = 28;

    /**
     * The period containing `$reference`. With a cutoff, a reference on
     * day `> C` belongs to the period ending next month; on day `<= C`,
     * the period ending this month.
     */
    public function resolve(CarbonInterface $reference, ?int $cutoff): ReportingPeriod
    {
        $reference = Carbon::parse($reference)->startOfDay();
        $cutoff = $this->normaliseCutoff($cutoff);

        if ($cutoff === null) {
            $start = $reference->copy()->startOfMonth();
            $end = $reference->copy()->endOfMonth();

            return $this->make($start, $end);
        }

        $endMonthAnchor = $reference->day <= $cutoff
            ? $reference->copy()
            : $reference->copy()->addMonthNoOverflow();

        $end = $endMonthAnchor->setDay($cutoff)->startOfDay();
        $start = $end->copy()->subMonthNoOverflow()->addDay()->startOfDay();

        return $this->make($start, $end);
    }

    /** The current period, from the organization's own clock (never the caller's). */
    public function current(?OrganizationSettings $settings = null): ReportingPeriod
    {
        $settings ??= OrganizationSettings::current();

        return $this->resolve(
            Carbon::now($settings->timezone),
            $settings->reporting_month_cutoff_day,
        );
    }

    /**
     * The period identified by a `YYYY-MM` key (the month it ends in).
     * Unparseable keys fall back to the current period.
     */
    public function forKey(string $key, ?int $cutoff): ReportingPeriod
    {
        $cutoff = $this->normaliseCutoff($cutoff);

        try {
            $endMonth = Carbon::createFromFormat('Y-m', $key)->startOfMonth();
        } catch (\Throwable) {
            return $this->current();
        }

        // A reference guaranteed to sit inside the period ending that month.
        $reference = $cutoff === null
            ? $endMonth
            : $endMonth->copy()->setDay($cutoff);

        return $this->resolve($reference, $cutoff);
    }

    /** The period `$delta` months before (negative) or after (positive) `$period`. */
    public function step(ReportingPeriod $period, int $delta, ?int $cutoff): ReportingPeriod
    {
        $shiftedKey = Carbon::createFromFormat('Y-m', $period->key)
            ->addMonthsNoOverflow($delta)
            ->format('Y-m');

        return $this->forKey($shiftedKey, $cutoff);
    }

    private function make(Carbon $start, Carbon $end): ReportingPeriod
    {
        return new ReportingPeriod(
            key: $end->format('Y-m'),
            label: $end->isoFormat('MMMM YYYY'),
            startDate: $start,
            endDate: $end,
        );
    }

    private function normaliseCutoff(?int $cutoff): ?int
    {
        if ($cutoff === null || $cutoff <= 0) {
            return null;
        }

        return min($cutoff, self::MAX_CUTOFF);
    }
}
