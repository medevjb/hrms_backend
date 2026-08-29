<?php

use App\Enums\PermissionName;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

function userWithSettingsPermission(PermissionName $permission): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('reading organization settings requires settings.manage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/settings/organization')->assertStatus(403);
});

test('a user with settings.manage can read and update organization settings', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->getJson('/api/v1/settings/organization')
        ->assertOk()
        ->assertJsonPath('data.timezone', 'UTC');

    $response = $this->actingAs($user)->putJson('/api/v1/settings/organization', [
        'timezone' => 'Asia/Dhaka',
        'currency' => 'BDT',
        'weekend_days' => ['friday', 'saturday'],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.timezone', 'Asia/Dhaka');
    $response->assertJsonPath('data.currency', 'BDT');
    $response->assertJsonPath('data.weekend_days', ['friday', 'saturday']);
});

test('attendance.settings.manage is a distinct permission from settings.manage', function () {
    $user = userWithSettingsPermission(PermissionName::SettingsManage);

    $this->actingAs($user)->getJson('/api/v1/settings/attendance')->assertStatus(403);
});

test('a user with attendance.settings.manage can update the late grace period', function () {
    $user = userWithSettingsPermission(PermissionName::AttendanceSettingsManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/attendance', ['late_grace_minutes' => 15]);

    $response->assertOk();
    $response->assertJsonPath('data.late_grace_minutes', 15);
    expect(OrganizationSettings::current()->late_grace_minutes)->toBe(15);
});

test('late_grace_minutes must be between 0 and 120', function () {
    $user = userWithSettingsPermission(PermissionName::AttendanceSettingsManage);

    $this->actingAs($user)->putJson('/api/v1/settings/attendance', ['late_grace_minutes' => 500])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['late_grace_minutes']);
});

test('hourly overtime is disabled by default (§47)', function () {
    $user = userWithSettingsPermission(PermissionName::OvertimePolicyManage);

    $this->actingAs($user)->getJson('/api/v1/settings/overtime')
        ->assertOk()
        ->assertJsonPath('data.hourly_overtime_enabled', false)
        ->assertJsonPath('data.overtime_enabled', true)
        ->assertJsonPath('data.weekend_overtime_enabled', true)
        ->assertJsonPath('data.holiday_overtime_enabled', true);
});

test('a user with overtime.policy.manage can update overtime settings, including enabling hourly overtime', function () {
    $user = userWithSettingsPermission(PermissionName::OvertimePolicyManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/overtime', [
        'hourly_overtime_enabled' => true,
        'overtime_hourly_rate_mode' => 'FIXED',
        'overtime_hourly_fixed_rate' => '250.5000',
        'overtime_full_day_minutes' => 420,
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.hourly_overtime_enabled', true);
    $response->assertJsonPath('data.overtime_hourly_rate_mode', 'FIXED');
    $response->assertJsonPath('data.overtime_full_day_minutes', 420);
});

test('a user with payroll.settings.manage can update the payroll cutoff day', function () {
    $user = userWithSettingsPermission(PermissionName::PayrollSettingsManage);

    $response = $this->actingAs($user)->putJson('/api/v1/settings/payroll', ['payroll_cutoff_day' => 25]);

    $response->assertOk();
    $response->assertJsonPath('data.payroll_cutoff_day', 25);
});
