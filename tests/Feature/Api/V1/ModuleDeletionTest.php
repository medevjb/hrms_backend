<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;

/**
 * Config/catalog module deletion — every one blocks when the row is still
 * referenced by real data (chosen over a cascade, so history is never
 * destroyed).
 */
function delUser(array $permissions): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Del '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

test('an empty department can be deleted; one with teams cannot', function () {
    $admin = delUser([PermissionName::DepartmentManage->value]);
    $empty = Department::factory()->create();
    $withTeams = Department::factory()->create();
    Team::factory()->create(['department_id' => $withTeams->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/departments/{$empty->id}")->assertNoContent();
    $this->actingAs($admin)->deleteJson("/api/v1/departments/{$withTeams->id}")->assertStatus(409);
});

test('a team with no members can be deleted; one with members cannot', function () {
    $admin = delUser([PermissionName::TeamManage->value]);
    $empty = Team::factory()->create();
    $withMembers = Team::factory()->create();
    TeamMember::factory()->create(['team_id' => $withMembers->id, 'employee_id' => Employee::factory()]);

    $this->actingAs($admin)->deleteJson("/api/v1/teams/{$empty->id}")->assertNoContent();
    $this->actingAs($admin)->deleteJson("/api/v1/teams/{$withMembers->id}")->assertStatus(409);
});

test('a shift referenced by attendance history cannot be deleted', function () {
    $admin = delUser([PermissionName::ShiftManage->value]);
    $unused = Shift::factory()->create();
    $used = Shift::factory()->create();
    AttendanceRecord::factory()->create(['shift_id' => $used->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/shifts/{$unused->id}")->assertNoContent();
    $this->actingAs($admin)->deleteJson("/api/v1/shifts/{$used->id}")->assertStatus(409);
});

test('deleting a shift needs shift.manage', function () {
    $shift = Shift::factory()->create();
    $this->actingAs(delUser([PermissionName::ShiftView->value]))
        ->deleteJson("/api/v1/shifts/{$shift->id}")
        ->assertStatus(403);
});

test('a salary component in use cannot be deleted, and Basic never can', function () {
    $admin = delUser([PermissionName::PayrollSettingsManage->value]);
    $basic = SalaryComponent::factory()->basic()->create();
    $unused = SalaryComponent::factory()->create(['code' => 'BONUS']);
    $used = SalaryComponent::factory()->create(['code' => 'TRANSPORT']);
    EmployeeSalaryComponent::factory()->create(['salary_component_id' => $used->id]);

    $this->actingAs($admin)->deleteJson("/api/v1/salary-components/{$basic->id}")->assertStatus(409);
    $this->actingAs($admin)->deleteJson("/api/v1/salary-components/{$used->id}")->assertStatus(409);
    $this->actingAs($admin)->deleteJson("/api/v1/salary-components/{$unused->id}")->assertNoContent();
});

test('a salary component can be created and toggled active', function () {
    $admin = delUser([PermissionName::PayrollSettingsManage->value]);

    $created = $this->actingAs($admin)->postJson('/api/v1/salary-components', [
        'code' => 'SHIFT_ALLOWANCE', 'name' => 'Shift Allowance', 'type' => 'ALLOWANCE',
    ]);
    $created->assertStatus(201);
    $id = $created->json('data.id');

    $this->actingAs($admin)->putJson("/api/v1/salary-components/{$id}", ['is_active' => false])
        ->assertOk()->assertJsonPath('data.is_active', false);
});

test('a draft announcement can be deleted; a published one cannot', function () {
    $admin = delUser([PermissionName::AnnouncementCreate->value, PermissionName::AnnouncementView->value]);
    $draft = Announcement::factory()->create();
    $published = Announcement::factory()->published()->create();

    $this->actingAs($admin)->deleteJson("/api/v1/announcements/{$draft->id}")->assertNoContent();
    $this->actingAs($admin)->deleteJson("/api/v1/announcements/{$published->id}")->assertStatus(403);
});
