<?php

use App\Enums\AttendanceStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;

/**
 * docs/PRD.md §99/§11 — the V1 reports: JSON preview needs report.view,
 * CSV export needs report.export, and rows are scoped.
 */
function reportUser(array $permissions, Scope $scope = Scope::AllEmployees, ?int $scopeId = null): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Report '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope, 'scope_id' => $scopeId]);

    return $user;
}

test('listing report types requires report.view', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/reports')->assertStatus(403);

    $this->actingAs(reportUser([PermissionName::ReportView->value]))
        ->getJson('/api/v1/reports')
        ->assertOk()
        ->assertJsonFragment(['type' => 'attendance']);
});

test('the employee directory report returns rows for the caller', function () {
    Employee::factory()->count(3)->create();

    $this->actingAs(reportUser([PermissionName::ReportView->value]))
        ->getJson('/api/v1/reports/employee_directory')
        ->assertOk()
        ->assertJsonPath('data.type', 'employee_directory')
        ->assertJsonPath('data.total', 3);
});

test('an unknown report type is a 404', function () {
    $this->actingAs(reportUser([PermissionName::ReportView->value]))
        ->getJson('/api/v1/reports/nonsense')
        ->assertStatus(404);
});

test('the attendance report is scoped to the caller and filtered by date', function () {
    $team = Team::factory()->create();
    $tl = Employee::factory()->create();
    $team->update(['team_leader_id' => $tl->id]);
    $mine = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $mine->id]);
    $other = Employee::factory()->create();

    AttendanceRecord::factory()->create(['employee_id' => $mine->id, 'work_date' => '2026-08-10', 'status' => AttendanceStatus::Late]);
    AttendanceRecord::factory()->create(['employee_id' => $other->id, 'work_date' => '2026-08-10', 'status' => AttendanceStatus::Late]);
    AttendanceRecord::factory()->create(['employee_id' => $mine->id, 'work_date' => '2026-07-01', 'status' => AttendanceStatus::Late]);

    $user = reportUser([PermissionName::ReportView->value], Scope::Team, $team->id);

    $this->actingAs($user)
        ->getJson('/api/v1/reports/late_attendance?filter[date_from]=2026-08-01&filter[date_to]=2026-08-31')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('a report with no dates defaults to the current reporting month', function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'reporting_month_cutoff_day' => 25]);
    $this->travelTo('2026-09-10');

    $employee = Employee::factory()->create();
    // In the Aug 26 – Sep 25 window.
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-28', 'status' => AttendanceStatus::Late]);
    // Before it — belongs to the previous reporting month.
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-20', 'status' => AttendanceStatus::Late]);

    $this->actingAs(reportUser([PermissionName::ReportView->value]))
        ->getJson('/api/v1/reports/late_attendance')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('a report scoped to an explicit period key uses that reporting month', function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'reporting_month_cutoff_day' => 25]);
    $this->travelTo('2026-09-10');

    $employee = Employee::factory()->create();
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-20', 'status' => AttendanceStatus::Late]);

    $this->actingAs(reportUser([PermissionName::ReportView->value]))
        ->getJson('/api/v1/reports/late_attendance?filter[period]=2026-08')
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});

test('CSV export requires report.export and streams a downloadable file', function () {
    Employee::factory()->count(2)->create();

    $viewer = reportUser([PermissionName::ReportView->value]);
    $this->actingAs($viewer)->get('/api/v1/reports/employee_directory/export')->assertStatus(403);

    $exporter = reportUser([PermissionName::ReportView->value, PermissionName::ReportExport->value]);
    $response = $this->actingAs($exporter)->get('/api/v1/reports/employee_directory/export');

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain('Name,Designation,Department');
});

test('a department filter narrows the report', function () {
    $department = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id]);
    $inDept = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $inDept->id]);
    Employee::factory()->create();

    $this->actingAs(reportUser([PermissionName::ReportView->value]))
        ->getJson("/api/v1/reports/employee_directory?filter[department_id]={$department->id}")
        ->assertOk()
        ->assertJsonPath('data.total', 1);
});
