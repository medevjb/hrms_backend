<?php

use App\Enums\HolidayNoticeStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayNotice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\HolidayNoticeService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function hncUser(array $permissions): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'HR '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

function hncPendingNotice(): HolidayNotice
{
    Holiday::factory()->create(['date' => Carbon::parse('2026-09-01')->addDays(5)->toDateString(), 'active' => true]);
    app(HolidayNoticeService::class)->scanForUpcomingHolidays(Carbon::parse('2026-09-01'));

    return HolidayNotice::query()->sole();
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

test('listing holiday notices requires holiday.view', function () {
    hncPendingNotice();

    $this->actingAs(User::factory()->create())->getJson('/api/v1/holiday-notices')->assertStatus(403);

    $this->actingAs(hncUser([PermissionName::HolidayView->value]))
        ->getJson('/api/v1/holiday-notices')
        ->assertOk()
        ->assertJsonPath('data.0.status', 'PENDING_APPROVAL')
        ->assertJsonPath('meta.total', 1);
});

test('approving a holiday notice requires holiday.notice.approve', function () {
    $notice = hncPendingNotice();

    $this->actingAs(hncUser([PermissionName::HolidayView->value]))
        ->postJson("/api/v1/holiday-notices/{$notice->id}/approve")
        ->assertStatus(403);

    expect($notice->fresh()->status)->toBe(HolidayNoticeStatus::PendingApproval);
});

test('Head HR approves a notice and it becomes downloadable', function () {
    Employee::factory()->count(2)->create();
    $notice = hncPendingNotice();
    $headHr = hncUser([PermissionName::HolidayView->value, PermissionName::HolidayNoticeApprove->value]);

    $this->actingAs($headHr)
        ->postJson("/api/v1/holiday-notices/{$notice->id}/approve", [
            'closure_note' => 'Emergency line stays open.',
            'return_date' => '2026-09-08',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'PUBLISHED')
        ->assertJsonPath('data.signatory_name', $headHr->name)
        ->assertJsonPath('data.has_document', true);

    $this->actingAs($headHr)
        ->get("/api/v1/holiday-notices/{$notice->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('approving an already-published notice is a conflict', function () {
    $notice = hncPendingNotice();
    $headHr = hncUser([PermissionName::HolidayView->value, PermissionName::HolidayNoticeApprove->value]);

    $this->actingAs($headHr)->postJson("/api/v1/holiday-notices/{$notice->id}/approve")->assertOk();
    $this->actingAs($headHr)->postJson("/api/v1/holiday-notices/{$notice->id}/approve")->assertStatus(409);
});

test('a pending notice has no downloadable document', function () {
    $notice = hncPendingNotice();

    $this->actingAs(hncUser([PermissionName::HolidayView->value]))
        ->get("/api/v1/holiday-notices/{$notice->id}/download")
        ->assertStatus(404);
});
