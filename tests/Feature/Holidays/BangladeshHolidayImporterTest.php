<?php

use App\Enums\HolidaySource;
use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Services\BangladeshHolidayImporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow('2026-01-15 09:00:00');

    config(['services.google_holidays.bd_ics_url' => 'https://calendar.example/bd.ics']);

    Http::fake([
        'calendar.example/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/bd-holidays.ics')),
            200,
        ),
    ]);
});

afterEach(fn () => Carbon::setTestNow());

test('imports public holidays and skips observances and past years', function () {
    $result = app(BangladeshHolidayImporter::class)->import();

    expect($result)->toBe(['created' => 2, 'updated' => 0, 'skipped' => 0]);

    $titles = Holiday::query()->orderBy('date')->pluck('title')->all();
    expect($titles)->toBe(['International Mother Language Day', 'Eid ul-Fitr']);

    expect(Holiday::query()->where('title', 'Chaitra Sankranti')->exists())->toBeFalse();
    expect(Holiday::query()->where('title', 'Victory Day')->exists())->toBeFalse();
});

test('imported rows are tagged GOOGLE_BD with the event uid and a sync time', function () {
    app(BangladeshHolidayImporter::class)->import();

    $holiday = Holiday::query()->where('title', 'International Mother Language Day')->sole();

    expect($holiday->source)->toBe(HolidaySource::GoogleBd)
        ->and($holiday->external_uid)->toBe('20260221_intlmotherlanguageday00000@google.com')
        ->and($holiday->date->toDateString())->toBe('2026-02-21')
        ->and($holiday->synced_at)->not->toBeNull();
});

test('religious days are classified RELIGIOUS, civil days NATIONAL', function () {
    app(BangladeshHolidayImporter::class)->import();

    expect(Holiday::query()->where('title', 'Eid ul-Fitr')->sole()->type)->toBe(HolidayType::Religious)
        ->and(Holiday::query()->where('title', 'International Mother Language Day')->sole()->type)
        ->toBe(HolidayType::National);
});

test('re-running is idempotent and never duplicates', function () {
    $importer = app(BangladeshHolidayImporter::class);

    $importer->import();
    $second = $importer->import();

    expect($second)->toBe(['created' => 0, 'updated' => 0, 'skipped' => 0])
        ->and(Holiday::query()->count())->toBe(2);
});

test('a shifted date on the same event updates the row in place', function () {
    app(BangladeshHolidayImporter::class)->import();

    Holiday::query()->where('external_uid', '20260321_eidulfitrbangladesh0000000@google.com')
        ->update(['date' => '2026-03-19']);

    $result = app(BangladeshHolidayImporter::class)->import();

    expect($result['updated'])->toBe(1)
        ->and(Holiday::query()->where('external_uid', '20260321_eidulfitrbangladesh0000000@google.com')
            ->sole()->date->toDateString())->toBe('2026-03-21');
});

test('a manually added holiday on the same date is left untouched', function () {
    $manual = Holiday::factory()->create([
        'title' => 'Company Eid Break',
        'date' => '2026-03-21',
        'type' => HolidayType::Company,
    ]);

    $result = app(BangladeshHolidayImporter::class)->import();

    expect($result['skipped'])->toBe(1);

    $manual->refresh();
    expect($manual->title)->toBe('Company Eid Break')
        ->and($manual->source)->toBe(HolidaySource::Manual)
        ->and(Holiday::query()->whereDate('date', '2026-03-21')->count())->toBe(1);
});

test('holidays:import-bd command runs the importer', function () {
    $this->artisan('holidays:import-bd')
        ->expectsOutputToContain('2 created')
        ->assertSuccessful();

    expect(Holiday::query()->count())->toBe(2);
});
