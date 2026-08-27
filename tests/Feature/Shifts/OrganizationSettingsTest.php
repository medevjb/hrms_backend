<?php

use App\Models\OrganizationSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

test('current() creates the singleton row with its defaults on first access', function () {
    expect(OrganizationSettings::query()->count())->toBe(0);

    $settings = OrganizationSettings::current();

    expect(OrganizationSettings::query()->count())->toBe(1);
    expect($settings->late_grace_minutes)->toBe(10);
    expect($settings->hourly_overtime_enabled)->toBeFalse();
    expect($settings->weekend_days)->toBe(['saturday', 'sunday']);
});

test('current() returns the same row on repeated calls, not a new one each time', function () {
    $first = OrganizationSettings::current();
    $second = OrganizationSettings::current();

    expect($second->id)->toBe($first->id);
    expect(OrganizationSettings::query()->count())->toBe(1);
});

test('saving invalidates the cache so the next current() reflects the change', function () {
    $settings = OrganizationSettings::current();
    $settings->update(['late_grace_minutes' => 20]);

    expect(OrganizationSettings::current()->late_grace_minutes)->toBe(20);
});

test('isWeekend checks the configured weekend_days, not a hard-coded day', function () {
    $settings = OrganizationSettings::current();
    $settings->update(['weekend_days' => ['friday']]);

    expect($settings->isWeekend(Carbon::parse('2026-08-28')))->toBeTrue(); // a Friday
    expect($settings->isWeekend(Carbon::parse('2026-08-29')))->toBeFalse(); // a Saturday
});

test('current() caches raw attributes, not a serialized model object, so a stale/incompatible cached model can never break it', function () {
    $settings = OrganizationSettings::current();

    // Simulates what a `rememberForever`'d object would leave behind after
    // a deploy changes the class shape: found this exact failure — an old
    // serialized OrganizationSettings sitting in the database cache driver
    // unserialized as __PHP_Incomplete_Class and 500'd every request that
    // touches settings. A plain attributes array has no class shape to go
    // stale, so re-seeding the cache with one directly must still resolve
    // cleanly through newFromBuilder().
    Cache::forget('organization_settings');
    Cache::forever('organization_settings', $settings->getAttributes());

    $resolved = OrganizationSettings::current();

    expect($resolved->id)->toBe($settings->id);
    expect($resolved->late_grace_minutes)->toBe($settings->late_grace_minutes);
    expect($resolved->weekend_days)->toBe($settings->weekend_days);
});
