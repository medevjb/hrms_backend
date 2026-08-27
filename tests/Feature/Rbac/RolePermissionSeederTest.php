<?php

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;

test('it seeds every role from the PRD and every permission in the enum', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::pluck('name')->all())->toEqualCanonicalizing([
        'Admin',
        'Head of HR',
        'HR',
        'Operation Manager',
        'Team Leader',
        'Team Member',
        'System Admin / DevOps',
    ]);

    expect(Permission::count())->toBe(count(PermissionName::cases()));
});

test('admin holds every permission', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = Role::where('name', 'Admin')->firstOrFail();

    expect($admin->permissions()->count())->toBe(count(PermissionName::cases()));
});

test('team member holds only self-scoped, low-privilege permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    $teamMember = Role::where('name', 'Team Member')->firstOrFail();
    $names = $teamMember->permissions()->pluck('name')->all();

    expect($names)->toEqualCanonicalizing([
        PermissionName::AttendanceView->value,
        PermissionName::LeaveRequest->value,
        PermissionName::HolidayView->value,
        PermissionName::AnnouncementView->value,
        PermissionName::PayslipViewSelf->value,
    ]);

    expect($names)->not->toContain(PermissionName::PayrollFinalize->value);
    expect($names)->not->toContain(PermissionName::EmployeeFinancialManage->value);
});

test('running the seeder twice does not duplicate roles or permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect(Role::count())->toBe(7);
    expect(Permission::count())->toBe(count(PermissionName::cases()));
});
