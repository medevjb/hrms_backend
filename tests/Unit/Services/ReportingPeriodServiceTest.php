<?php

use App\Services\ReportingPeriodService;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §85 — the reporting-month resolver. Mirrors the frontend
 * table in `lib/reporting-period.test.ts`; the two must agree.
 */
beforeEach(function () {
    $this->service = new ReportingPeriodService;
});

test('a date after the cutoff belongs to the period ending next month', function () {
    $period = $this->service->resolve(Carbon::parse('2026-08-26'), 25);

    expect($period->toArray())->toBe([
        'key' => '2026-09',
        'label' => 'September 2026',
        'start_date' => '2026-08-26',
        'end_date' => '2026-09-25',
    ]);
});

test('a date on or before the cutoff belongs to the period ending this month', function () {
    expect($this->service->resolve(Carbon::parse('2026-09-25'), 25)->key)->toBe('2026-09')
        ->and($this->service->resolve(Carbon::parse('2026-09-10'), 25)->key)->toBe('2026-09')
        ->and($this->service->resolve(Carbon::parse('2026-09-01'), 25)->startDate->toDateString())->toBe('2026-08-26');
});

test('a null cutoff resolves to the calendar month', function () {
    $period = $this->service->resolve(Carbon::parse('2026-09-10'), null);

    expect($period->toArray())->toBe([
        'key' => '2026-09',
        'label' => 'September 2026',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
    ]);
});

test('a zero or negative cutoff is treated as null', function () {
    expect($this->service->resolve(Carbon::parse('2026-09-10'), 0)->startDate->toDateString())->toBe('2026-09-01')
        ->and($this->service->resolve(Carbon::parse('2026-09-10'), -3)->startDate->toDateString())->toBe('2026-09-01');
});

test('a cutoff above 28 is clamped to 28', function () {
    expect($this->service->resolve(Carbon::parse('2026-09-10'), 31)->endDate->toDateString())->toBe('2026-09-28');
});

test('consecutive periods are contiguous with no gap or overlap', function () {
    $cutoff = 28;
    $keys = ['2026-01', '2026-02', '2026-03', '2026-04'];

    $previousEnd = null;
    foreach ($keys as $key) {
        $period = $this->service->forKey($key, $cutoff);

        if ($previousEnd !== null) {
            expect($period->startDate->toDateString())
                ->toBe($previousEnd->copy()->addDay()->toDateString());
        }

        $previousEnd = $period->endDate;
    }
});

test('a leap-day is not orphaned when the cutoff is 28', function () {
    // Feb 2028 is a leap year: the Feb period ends 28 Feb, the March
    // period must pick up 29 Feb rather than skipping it.
    $march = $this->service->forKey('2028-03', 28);

    expect($march->startDate->toDateString())->toBe('2028-02-29');
});

test('stepping moves to the adjacent period and relabels it', function () {
    $september = $this->service->forKey('2026-09', 25);

    $august = $this->service->step($september, -1, 25);
    $october = $this->service->step($september, 1, 25);

    expect($august->toArray())->toBe([
        'key' => '2026-08',
        'label' => 'August 2026',
        'start_date' => '2026-07-26',
        'end_date' => '2026-08-25',
    ])->and($october->key)->toBe('2026-10')
        ->and($october->startDate->toDateString())->toBe('2026-09-26');
});

test('contains is inclusive of both boundaries', function () {
    $period = $this->service->forKey('2026-09', 25);

    expect($period->contains(Carbon::parse('2026-08-26')))->toBeTrue()
        ->and($period->contains(Carbon::parse('2026-09-25')))->toBeTrue()
        ->and($period->contains(Carbon::parse('2026-08-25')))->toBeFalse()
        ->and($period->contains(Carbon::parse('2026-09-26')))->toBeFalse();
});
