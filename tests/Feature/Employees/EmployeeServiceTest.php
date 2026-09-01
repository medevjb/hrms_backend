<?php

use App\Enums\EmployeeStatus;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Notifications\EmployeeInvitationNotification;
use App\Services\EmployeeService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('inviting creates a paired user nobody can log in as yet, and an INVITED employee', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $employee = app(EmployeeService::class)->invite([
        'email' => 'newhire@example.com',
        'first_name' => 'Nadia',
        'last_name' => 'Rahman',
        'joining_date' => '2026-09-01',
        'designation' => 'Recruiter',
        'employment_type' => 'FULL_TIME',
    ], $admin);

    expect($employee->status)->toBe(EmployeeStatus::Invited);
    expect($employee->employee_code)->toStartWith('EMP-');
    expect($employee->user->email)->toBe('newhire@example.com');
    expect(Hash::check('password', $employee->user->password))->toBeFalse();

    Notification::assertSentTo($employee->user, EmployeeInvitationNotification::class);
});

test('inviting grants the Team Member role over the new hire\'s own records', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $employee = app(EmployeeService::class)->invite([
        'email' => 'selfservice@example.com',
        'first_name' => 'Self', 'last_name' => 'Service',
        'joining_date' => '2026-09-01', 'designation' => 'X', 'employment_type' => 'FULL_TIME',
    ], $admin);

    $grant = $employee->user->roleAssignments()->with('role')->firstOrFail();
    expect($grant->role->name)->toBe('Team Member');
    expect($grant->scope)->toBe(Scope::Self);

    // The baseline self-service permissions the sidebar and calendar need.
    expect($employee->user->hasPermission('leave.request'))->toBeTrue();
    expect($employee->user->hasPermission('attendance.view'))->toBeTrue();
    expect($employee->user->hasPermission('payslip.view_self'))->toBeTrue();
});

test('inviting a second employee reuses the one Team Member role', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $first = app(EmployeeService::class)->invite([
        'email' => 'first@example.com', 'first_name' => 'A', 'last_name' => 'B',
        'joining_date' => '2026-09-01', 'designation' => 'X', 'employment_type' => 'FULL_TIME',
    ], $admin);
    $second = app(EmployeeService::class)->invite([
        'email' => 'second@example.com', 'first_name' => 'C', 'last_name' => 'D',
        'joining_date' => '2026-09-01', 'designation' => 'X', 'employment_type' => 'FULL_TIME',
    ], $admin);

    expect($first->user->roleAssignments()->first()->role_id)
        ->toBe($second->user->roleAssignments()->first()->role_id);
    expect(Role::where('name', 'Team Member')->count())->toBe(1);
});

test('inviting records the initial status history entry', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $employee = app(EmployeeService::class)->invite([
        'email' => 'newhire2@example.com',
        'first_name' => 'A', 'last_name' => 'B',
        'joining_date' => '2026-09-01', 'designation' => 'X', 'employment_type' => 'FULL_TIME',
    ], $admin);

    $entry = $employee->statusHistory()->firstOrFail();
    expect($entry->from_status)->toBeNull();
    expect($entry->to_status)->toBe(EmployeeStatus::Invited);
    expect($entry->changed_by)->toBe($admin->id);
});

test('the invitation url points at the frontend reset-password page', function () {
    Notification::fake();
    $admin = User::factory()->create();

    $employee = app(EmployeeService::class)->invite([
        'email' => 'newhire3@example.com',
        'first_name' => 'A', 'last_name' => 'B',
        'joining_date' => '2026-09-01', 'designation' => 'X', 'employment_type' => 'FULL_TIME',
    ], $admin);

    Notification::assertSentTo($employee->user, function (EmployeeInvitationNotification $notification) use ($employee) {
        $url = $notification->toMail($employee->user)->actionUrl;
        expect($url)->toStartWith('http://localhost:3000/reset-password?token=')
            ->and($url)->toContain('&email='.urlencode($employee->user->email));

        return true;
    });
});

test('transitionStatus updates the employee and logs the transition', function () {
    $admin = User::factory()->create();
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Probation]);

    app(EmployeeService::class)->transitionStatus($employee, EmployeeStatus::Active, 'Probation completed', $admin);

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
    $entry = $employee->statusHistory()->latest()->firstOrFail();
    expect($entry->from_status)->toBe(EmployeeStatus::Probation);
    expect($entry->to_status)->toBe(EmployeeStatus::Active);
    expect($entry->reason)->toBe('Probation completed');
});

test('transfer ends the old membership and starts a new one', function () {
    $oldTeam = Team::factory()->create();
    $newTeam = Team::factory()->create();
    $employee = Employee::factory()->create();
    $employee->teamMemberships()->create(['team_id' => $oldTeam->id, 'started_at' => '2026-01-01']);

    app(EmployeeService::class)->transfer($employee, $newTeam, '2026-06-01');

    expect($employee->fresh()->currentTeam()->id)->toBe($newTeam->id);
    expect($employee->teamMemberships()->count())->toBe(2);
    expect($employee->teamMemberships()->where('team_id', $oldTeam->id)->first()->ended_at->toDateString())
        ->toBe('2026-06-01');
});

test('transfer works for an employee with no prior team', function () {
    $team = Team::factory()->create();
    $employee = Employee::factory()->create();

    app(EmployeeService::class)->transfer($employee, $team);

    expect($employee->fresh()->currentTeam()->id)->toBe($team->id);
});

test('acceptInvitation goes to ACTIVE when there is no confirmation date', function () {
    $employee = Employee::factory()->create(['status' => EmployeeStatus::Invited, 'confirmation_date' => null]);

    app(EmployeeService::class)->acceptInvitation($employee);

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Active);
});

test('acceptInvitation goes to PROBATION when a confirmation date is set', function () {
    $employee = Employee::factory()->create([
        'status' => EmployeeStatus::Invited,
        'confirmation_date' => '2026-12-01',
    ]);

    app(EmployeeService::class)->acceptInvitation($employee);

    expect($employee->fresh()->status)->toBe(EmployeeStatus::Probation);
});
