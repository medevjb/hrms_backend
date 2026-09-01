<?php

use App\Enums\PermissionName;
use App\Models\Holiday;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

function userWithHolidayPermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('listing holidays requires holiday.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/holidays')->assertStatus(403);
});

test('creating a holiday requires holiday.manage', function () {
    $user = userWithHolidayPermission(PermissionName::HolidayView);

    $this->actingAs($user)->postJson('/api/v1/holidays', [
        'title' => 'Independence Day', 'date' => '2026-12-16', 'type' => 'NATIONAL',
    ])->assertStatus(403);
});

test('a user with holiday.manage can create a holiday', function () {
    $user = userWithHolidayPermission(PermissionName::HolidayManage);

    $response = $this->actingAs($user)->postJson('/api/v1/holidays', [
        'title' => 'Independence Day',
        'date' => '2026-12-16',
        'type' => 'NATIONAL',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.title', 'Independence Day');
    $response->assertJsonPath('data.date', '2026-12-16');
    $response->assertJsonPath('data.type', 'NATIONAL');
    $response->assertJsonPath('data.active', true);
});

test('updating a holiday', function () {
    $holiday = Holiday::factory()->create(['title' => 'Old Name']);
    $user = userWithHolidayPermission(PermissionName::HolidayManage);

    $response = $this->actingAs($user)->putJson("/api/v1/holidays/{$holiday->id}", ['title' => 'New Name']);

    $response->assertOk();
    $response->assertJsonPath('data.title', 'New Name');
});

test('importing Bangladesh holidays requires holiday.manage', function () {
    $user = userWithHolidayPermission(PermissionName::HolidayView);

    $this->actingAs($user)->postJson('/api/v1/holidays/import')->assertStatus(403);
});

test('a user with holiday.manage can import Bangladesh holidays from the Google feed', function () {
    Carbon::setTestNow('2026-01-15 09:00:00');
    config(['services.google_holidays.bd_ics_url' => 'https://calendar.example/bd.ics']);
    Http::fake([
        'calendar.example/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/bd-holidays.ics')),
            200,
        ),
    ]);

    $user = userWithHolidayPermission(PermissionName::HolidayManage);

    $response = $this->actingAs($user)->postJson('/api/v1/holidays/import');

    $response->assertOk();
    $response->assertJsonPath('data.created', 2);
    $response->assertJsonPath('data.updated', 0);
    $response->assertJsonPath('data.skipped', 0);

    expect(Holiday::query()->count())->toBe(2);

    Carbon::setTestNow();
});

test('deleting a holiday requires holiday.manage and returns 204', function () {
    $holiday = Holiday::factory()->create();
    $user = userWithHolidayPermission(PermissionName::HolidayManage);

    $response = $this->actingAs($user)->deleteJson("/api/v1/holidays/{$holiday->id}");

    $response->assertNoContent();
    expect(Holiday::query()->find($holiday->id))->toBeNull();
});
