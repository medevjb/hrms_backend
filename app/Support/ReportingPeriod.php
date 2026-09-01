<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One resolved reporting month (docs/PRD.md §85). With a null cutoff this
 * is just a calendar month; with a cutoff `C` it is the window
 * `[C+1 of the previous month .. C of this month]`, always identified and
 * labelled by the month it ends in (cutoff 25 → the window ending 25 Sep
 * is "September"). Every date-scoped view, aggregate, and API default
 * range across the product is expressed as one of these.
 */
readonly class ReportingPeriod
{
    public function __construct(
        public string $key,
        public string $label,
        public Carbon $startDate,
        public Carbon $endDate,
    ) {}

    /** Is `$date` inside this period (inclusive, day granularity)? */
    public function contains(CarbonInterface $date): bool
    {
        $day = Carbon::parse($date)->startOfDay();

        return $day->betweenIncluded($this->startDate, $this->endDate);
    }

    /**
     * The shape every API resource and response echoes so the frontend
     * renders labels and ranges without repeating the math.
     *
     * @return array{key: string, label: string, start_date: string, end_date: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'start_date' => $this->startDate->toDateString(),
            'end_date' => $this->endDate->toDateString(),
        ];
    }
}
