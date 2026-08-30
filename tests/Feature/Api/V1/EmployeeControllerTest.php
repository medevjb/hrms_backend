<?php

use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\AttendanceRecord;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeStatusHistory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\EmployeeInvitationNotification;
use Illuminate\Support\Facades\Notification;

function userWithPermission(PermissionName $permission, Scope $scope = Scope::AllEmployees, ?int $scopeId = null): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();
    $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
    $role->permissions()->attach($perm);
    UserRole::factory()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope' => $scope,
        'scope_id' => $scopeId,
    ]);

    return $user;
}

test('a user without employee.view cannot list employees', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/employees');

    $response->assertStatus(403);
});

test('a user with employee.view @ ALL_EMPLOYEES sees everyone', function () {
    $user = userWithPermission(PermissionName::EmployeeView);
    Employee::factory()->count(3)->create();

    $response = $this->actingAs($user)->getJson('/api/v1/employees');

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
});

test('filter[search] matches name, code, designation, or email', function () {
    $user = userWithPermission(PermissionName::EmployeeView);
    $target = Employee::factory()
        ->for(User::factory()->state(['email' => 'aisha.k@example.com']))
        ->create(['first_name' => 'Aisha', 'last_name' => 'Karim', 'employee_code' => 'EMP-90001', 'designation' => 'Data Analyst']);
    Employee::factory()->count(2)->create(['designation' => 'Engineer']);

    $byName = $this->actingAs($user)->getJson('/api/v1/employees?filter[search]=aisha kar');
    $byName->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);

    $byCode = $this->actingAs($user)->getJson('/api/v1/employees?filter[search]=90001');
    $byCode->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);

    $byEmail = $this->actingAs($user)->getJson('/api/v1/employees?filter[search]=aisha.k@example');
    $byEmail->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $target->id);
});

test('filter[employment_type] and filter[shift_id] narrow the list', function () {
    $user = userWithPermission(PermissionName::EmployeeView);
    $shift = Shift::factory()->create();
    $intern = Employee::factory()->create(['employment_type' => 'INTERN']);
    $intern->shiftAssignments()->create(['shift_id' => $shift->id, 'started_at' => now()->toDateString()]);
    Employee::factory()->count(2)->create(['employment_type' => 'FULL_TIME']);

    $this->actingAs($user)->getJson('/api/v1/employees?filter[employment_type]=INTERN')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $intern->id);

    $this->actingAs($user)->getJson("/api/v1/employees?filter[shift_id]={$shift->id}")
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $intern->id);
});

test('filter[joined_from]/filter[joined_to] and filter[unassigned] work', function () {
    $user = userWithPermission(PermissionName::EmployeeView);
    $recent = Employee::factory()->create(['joining_date' => '2026-06-15']);
    Employee::factory()->create(['joining_date' => '2023-01-10']);
    TeamMember::factory()->create(['employee_id' => Employee::factory()->create()->id]);

    $this->actingAs($user)->getJson('/api/v1/employees?filter[joined_from]=2026-01-01')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $recent->id);

    // The two factory employees with no TeamMember row are unassigned.
    $this->actingAs($user)->getJson('/api/v1/employees?filter[unassigned]=1')
        ->assertJsonCount(2, 'data');
});

test('sort=joined_desc orders newest hire first and per_page is capped at 100', function () {
    $user = userWithPermission(PermissionName::EmployeeView);
    $older = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $newer = Employee::factory()->create(['joining_date' => '2026-01-01']);

    $sorted = $this->actingAs($user)->getJson('/api/v1/employees?sort=joined_desc');
    $sorted->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);

    $this->actingAs($user)->getJson('/api/v1/employees?per_page=5000')
        ->assertJsonPath('meta.per_page', 100);
});

test('a user with employee.view @ TEAM only sees their teams members', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $inTeam = Employee::factory()->create();
    $outsider = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $inTeam->id]);
    TeamMember::factory()->create(['team_id' => $otherTeam->id, 'employee_id' => $outsider->id]);

    $user = userWithPermission(PermissionName::EmployeeView, Scope::Team, $team->id);

    $response = $this->actingAs($user)->getJson('/api/v1/employees');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.id', $inTeam->id);
});

test('showing an out-of-scope employee is 404, not 403', function () {
    $team = Team::factory()->create();
    $outsider = Employee::factory()->create();
    $user = userWithPermission(PermissionName::EmployeeView, Scope::Team, $team->id);

    $response = $this->actingAs($user)->getJson("/api/v1/employees/{$outsider->id}");

    $response->assertStatus(404);
});

test('showing an in-scope employee returns the derived team/department/leader', function () {
    $operationManager = Employee::factory()->create();
    $department = Department::factory()->create(['operation_manager_id' => $operationManager->id]);
    $teamLeader = Employee::factory()->create();
    $team = Team::factory()->create(['department_id' => $department->id, 'team_leader_id' => $teamLeader->id]);
    $employee = Employee::factory()->create();
    TeamMember::factory()->create(['team_id' => $team->id, 'employee_id' => $employee->id]);

    $user = userWithPermission(PermissionName::EmployeeView);

    $response = $this->actingAs($user)->getJson("/api/v1/employees/{$employee->id}");

    $response->assertOk();
    $response->assertJsonPath('data.team.id', $team->id);
    $response->assertJsonPath('data.department.id', $department->id);
    $response->assertJsonPath('data.team_leader.id', $teamLeader->id);
    $response->assertJsonPath('data.operation_manager.id', $operationManager->id);
});

test('creating an employee without employee.create is forbidden', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/employees', [
        'email' => 'x@example.com', 'first_name' => 'A', 'last_name' => 'B',
        'joining_date' => '2026-09-01', 'designation' => 'X', 'employment_type' => 'FULL_TIME',
    ]);

    $response->assertStatus(403);
});

test('creating an employee invites them and returns 201', function () {
    Notification::fake();
    $user = userWithPermission(PermissionName::EmployeeCreate);

    $response = $this->actingAs($user)->postJson('/api/v1/employees', [
        'email' => 'newperson@example.com',
        'first_name' => 'New', 'last_name' => 'Person',
        'joining_date' => '2026-09-01', 'designation' => 'Engineer', 'employment_type' => 'FULL_TIME',
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.status', 'INVITED');
    $response->assertJsonPath('data.email', 'newperson@example.com');

    $invited = User::query()->where('email', 'newperson@example.com')->sole();
    Notification::assertSentTo($invited, EmployeeInvitationNotification::class);
});

test('creating an employee with a duplicate email is rejected', function () {
    $existing = Employee::factory()->create();
    $user = userWithPermission(PermissionName::EmployeeCreate);

    $response = $this->actingAs($user)->postJson('/api/v1/employees', [
        'email' => $existing->user->email,
        'first_name' => 'New', 'last_name' => 'Person',
        'joining_date' => '2026-09-01', 'designation' => 'Engineer', 'employment_type' => 'FULL_TIME',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

test('updating an out-of-scope employee is 404', function () {
    $team = Team::factory()->create();
    $outsider = Employee::factory()->create();
    $user = userWithPermission(PermissionName::EmployeeUpdate, Scope::Team, $team->id);

    $response = $this->actingAs($user)->putJson("/api/v1/employees/{$outsider->id}", ['designation' => 'Lead']);

    $response->assertStatus(404);
});

test('updating an in-scope employee persists the change', function () {
    $employee = Employee::factory()->create(['designation' => 'Engineer']);
    $user = userWithPermission(PermissionName::EmployeeUpdate);

    $response = $this->actingAs($user)->putJson("/api/v1/employees/{$employee->id}", ['designation' => 'Senior Engineer']);

    $response->assertOk();
    expect($employee->fresh()->designation)->toBe('Senior Engineer');
});

test('updating status writes a status history entry', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Probation]);
    $user = userWithPermission(PermissionName::EmployeeUpdate);

    $response = $this->actingAs($user)->patchJson("/api/v1/employees/{$employee->id}/status", [
        'status' => 'ACTIVE',
        'reason' => 'Probation completed',
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.status', 'ACTIVE');
    expect($employee->statusHistory()->latest()->first()->reason)->toBe('Probation completed');
});

test('deleting an employee without employee.archive is 404', function () {
    $employee = Employee::factory()->invited()->create();
    $user = userWithPermission(PermissionName::EmployeeView);

    $this->actingAs($user)->deleteJson("/api/v1/employees/{$employee->id}")->assertStatus(404);
});

test('an invited employee with no history can be deleted along with their user', function () {
    $employee = Employee::factory()->invited()->create();
    $userId = $employee->user_id;
    // Every real invite writes one "created" status row — deleting the
    // employee has to take it with them, not trip the FK constraint.
    EmployeeStatusHistory::factory()->create([
        'employee_id' => $employee->id,
        'to_status' => EmployeeStatus::Invited,
    ]);
    $user = userWithPermission(PermissionName::EmployeeArchive);

    $this->actingAs($user)
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertNoContent();

    expect(Employee::query()->find($employee->id))->toBeNull()
        ->and(User::query()->find($userId))->toBeNull()
        ->and(EmployeeStatusHistory::query()->where('employee_id', $employee->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', AuditAction::EmployeeDeleted)->exists())->toBeTrue();
});

test('an active employee cannot be deleted', function () {
    $employee = Employee::factory()->create(); // ACTIVE by default
    $user = userWithPermission(PermissionName::EmployeeArchive);

    $this->actingAs($user)
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertStatus(409);

    expect(Employee::query()->find($employee->id))->not->toBeNull();
});

test('an invited employee with attendance history cannot be deleted', function () {
    $employee = Employee::factory()->invited()->create();
    AttendanceRecord::factory()->create(['employee_id' => $employee->id]);
    $user = userWithPermission(PermissionName::EmployeeArchive);

    $this->actingAs($user)
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertStatus(409);
});
