<?php

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementType;
use App\Enums\HolidayNoticeStatus;
use App\Enums\HolidayReminderStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Announcement;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayNotice;
use App\Models\HolidayReminder;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\AnnouncementPublished;
use App\Notifications\HolidayNoticeAwaitingApproval;
use App\Services\HolidayNoticeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * docs/PRD.md §55/§56 — the five-day reminder scan, Head HR approval, PDF
 * generation, and the HOLIDAY announcement that publishing produces.
 */
function hnHeadHr(): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Head of HR']);
    $permission = Permission::query()->firstOrCreate(['name' => PermissionName::HolidayNoticeApprove->value]);
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

beforeEach(function () {
    Storage::fake('local');
});

test('the scan drafts a notice and notifies Head HR for a holiday five days out', function () {
    Notification::fake();
    $headHr = hnHeadHr();
    $today = Carbon::parse('2026-09-01');
    $holiday = Holiday::factory()->create(['date' => '2026-09-06', 'active' => true]);

    $count = app(HolidayNoticeService::class)->scanForUpcomingHolidays($today);

    expect($count)->toBe(1);

    $reminder = HolidayReminder::query()->where('holiday_id', $holiday->id)->sole();
    expect($reminder->status)->toBe(HolidayReminderStatus::Pending)
        ->and($reminder->lead_days_used)->toBe(5)
        ->and($reminder->head_hr_notified_at)->not->toBeNull();

    $notice = HolidayNotice::query()->where('holiday_id', $holiday->id)->sole();
    expect($notice->status)->toBe(HolidayNoticeStatus::PendingApproval)
        ->and($notice->reference)->toStartWith('HN-2026-')
        ->and($notice->return_date->toDateString())->toBe('2026-09-07');

    Notification::assertSentTo($headHr, HolidayNoticeAwaitingApproval::class);
});

test('the scan ignores holidays that are not exactly five days out', function () {
    hnHeadHr();
    $today = Carbon::parse('2026-09-01');
    Holiday::factory()->create(['date' => '2026-09-05', 'active' => true]);
    Holiday::factory()->create(['date' => '2026-09-08', 'active' => true]);
    Holiday::factory()->create(['date' => '2026-09-06', 'active' => false]);

    expect(app(HolidayNoticeService::class)->scanForUpcomingHolidays($today))->toBe(0);
});

test('the scan is idempotent across the five in-window days', function () {
    hnHeadHr();
    $holiday = Holiday::factory()->create(['date' => '2026-09-06', 'active' => true]);
    $service = app(HolidayNoticeService::class);

    $service->scanForUpcomingHolidays(Carbon::parse('2026-09-01'));
    $service->scanForUpcomingHolidays(Carbon::parse('2026-09-02'));

    expect(HolidayReminder::query()->where('holiday_id', $holiday->id)->count())->toBe(1)
        ->and(HolidayNotice::query()->where('holiday_id', $holiday->id)->count())->toBe(1);
});

test('approving a notice renders a PDF, publishes a HOLIDAY announcement, and notifies employees', function () {
    Notification::fake();
    $headHr = hnHeadHr();
    Employee::factory()->count(3)->create();
    $holiday = Holiday::factory()->create(['date' => '2026-09-06', 'active' => true, 'title' => 'Founders Day']);

    app(HolidayNoticeService::class)->scanForUpcomingHolidays(Carbon::parse('2026-09-01'));
    $notice = HolidayNotice::query()->sole();

    $updated = app(HolidayNoticeService::class)->approve(
        $notice->load('reminder'),
        $headHr,
        message: 'The office is closed for Founders Day.',
        closureNote: 'Security desk remains staffed.',
        returnDate: Carbon::parse('2026-09-08'),
    );

    expect($updated->status)->toBe(HolidayNoticeStatus::Published)
        ->and($updated->signatory_name)->toBe($headHr->name)
        ->and($updated->generated_at)->not->toBeNull()
        ->and($updated->message)->toBe('The office is closed for Founders Day.')
        ->and($updated->return_date->toDateString())->toBe('2026-09-08');

    Storage::disk('local')->assertExists($updated->file_path);

    $announcement = Announcement::query()->sole();
    expect($announcement->type)->toBe(AnnouncementType::Holiday)
        ->and($announcement->status)->toBe(AnnouncementStatus::Published)
        ->and($announcement->holiday_notice_id)->toBe($updated->id)
        ->and($updated->announcement_id)->toBe($announcement->id);

    expect($notice->reminder->fresh()->status)->toBe(HolidayReminderStatus::Actioned);

    Notification::assertSentTimes(AnnouncementPublished::class, 3);
});

test('a notice cannot be approved twice', function () {
    $headHr = hnHeadHr();
    $holiday = Holiday::factory()->create(['date' => '2026-09-06', 'active' => true]);
    app(HolidayNoticeService::class)->scanForUpcomingHolidays(Carbon::parse('2026-09-01'));
    $notice = HolidayNotice::query()->sole()->load('reminder');

    app(HolidayNoticeService::class)->approve($notice, $headHr);

    expect(fn () => app(HolidayNoticeService::class)->approve($notice->fresh()->load('reminder'), $headHr))
        ->toThrow(HttpException::class);
});

test('dismissing a notice closes the reminder so the scan will not reopen it', function () {
    $headHr = hnHeadHr();
    $holiday = Holiday::factory()->create(['date' => '2026-09-06', 'active' => true]);
    $service = app(HolidayNoticeService::class);
    $service->scanForUpcomingHolidays(Carbon::parse('2026-09-01'));
    $notice = HolidayNotice::query()->sole()->load('reminder');

    $service->dismiss($notice, $headHr);

    expect($notice->fresh()->status)->toBe(HolidayNoticeStatus::Dismissed)
        ->and($notice->reminder->fresh()->status)->toBe(HolidayReminderStatus::Dismissed);

    expect($service->scanForUpcomingHolidays(Carbon::parse('2026-09-02')))->toBe(0);
});
