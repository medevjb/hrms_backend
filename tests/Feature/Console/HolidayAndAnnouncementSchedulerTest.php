<?php

use App\Enums\AnnouncementAudienceType;
use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayNotice;
use App\Models\OrganizationSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §55/§57 — the two scheduled commands that drive the holiday
 * reminder and the announcement publish/expiry sweep.
 */
beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    OrganizationSettings::current()->update(['timezone' => 'UTC']);
});

test('holidays:scan-notices drafts a notice for a holiday five days out', function () {
    Carbon::setTestNow('2026-10-01 06:00:00');
    Holiday::factory()->create(['date' => '2026-10-06', 'active' => true]);

    $this->artisan('holidays:scan-notices')->assertSuccessful();

    expect(HolidayNotice::query()->count())->toBe(1);

    Carbon::setTestNow();
});

test('holidays:scan-notices accepts an explicit scan date', function () {
    Holiday::factory()->create(['date' => '2026-10-06', 'active' => true]);

    $this->artisan('holidays:scan-notices', ['date' => '2026-10-01'])->assertSuccessful();

    expect(HolidayNotice::query()->count())->toBe(1);
});

test('announcements:publish-due releases scheduled drafts', function () {
    Employee::factory()->create();
    Announcement::factory()->create([
        'audience_type' => AnnouncementAudienceType::All,
        'publish_at' => Carbon::now()->subMinutes(5),
    ]);

    $this->artisan('announcements:publish-due')->assertSuccessful();

    expect(Announcement::query()->sole()->status)->toBe(AnnouncementStatus::Published);
});
