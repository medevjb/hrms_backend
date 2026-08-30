<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\UserRole;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §69/§70/§147, §91 — the payroll workflow endpoints and their
 * permission boundaries.
 */
beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    SalaryComponent::factory()->basic()->create();
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'payroll_cutoff_day' => null]);
});

function wcGrant(User $user, array $permissions, Scope $scope = Scope::AllEmployees): void
{
    $role = Role::query()->firstOrCreate(['name' => 'WF '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope]);
}

function wcEmployee(): Employee
{
    $employee = Employee::factory()->create();
    $basicId = SalaryComponent::query()->where('code', 'BASIC')->value('id');
    app(SalaryService::class)->assign($employee, Carbon::parse('2026-01-01'), [$basicId => '30000'], null, User::factory()->create());

    return $employee->fresh();
}

function wcReleasedPeriod(): array
{
    $employee = wcEmployee();
    $period = app(PayrollService::class)->createPeriod(2026, 8);
    app(PayrollService::class)->generate($period);
    app(PayrollWorkflowService::class)->release($period->fresh());

    return [$period->fresh(), $period->entries()->first()];
}

test('review and release need payroll.prepare; finalise needs payroll.finalize', function () {
    $employee = wcEmployee();
    $preparer = User::factory()->create();
    wcGrant($preparer, [PermissionName::PayrollView->value, PermissionName::PayrollPrepare->value]);

    $periodId = $this->actingAs($preparer)->postJson('/api/v1/payroll/periods', ['year' => 2026, 'month' => 8])->json('data.id');
    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/generate");

    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/review")->assertOk()
        ->assertJsonPath('data.status', 'REVIEW');
    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/release")->assertOk()
        ->assertJsonPath('data.status', 'EMPLOYEE_CONFIRMATION');

    // preparer cannot finalise
    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/finalize")->assertStatus(403);

    $finalizer = User::factory()->create();
    wcGrant($finalizer, [PermissionName::PayrollView->value, PermissionName::PayrollFinalize->value]);
    $this->actingAs($finalizer)->postJson("/api/v1/payroll/periods/{$periodId}/finalize")->assertOk()
        ->assertJsonPath('data.status', 'FINALIZED');
});

test('an employee can acknowledge or dispute only their own entry', function () {
    [$period, $entry] = wcReleasedPeriod();
    $other = wcEmployee();
    wcGrant($other->user, [PermissionName::PayslipViewSelf->value], Scope::Self);

    $this->actingAs($other->user)
        ->postJson("/api/v1/payroll/entries/{$entry->id}/acknowledge")
        ->assertStatus(404);

    $mine = $entry->employee->user;
    wcGrant($mine, [PermissionName::PayslipViewSelf->value], Scope::Self);

    $this->actingAs($mine)
        ->postJson("/api/v1/payroll/entries/{$entry->id}/acknowledge")
        ->assertOk()
        ->assertJsonPath('data.acknowledgement_status', 'ACKNOWLEDGED');
});

test('a dispute is created, blocks finalisation, and is resolved by a dispute resolver', function () {
    [$period, $entry] = wcReleasedPeriod();
    $employee = $entry->employee->user;
    wcGrant($employee, [PermissionName::PayslipViewSelf->value], Scope::Self);

    $this->actingAs($employee)
        ->postJson("/api/v1/payroll/entries/{$entry->id}/dispute", ['reason' => 'Missing overtime'])
        ->assertStatus(201);

    $finalizer = User::factory()->create();
    wcGrant($finalizer, [PermissionName::PayrollView->value, PermissionName::PayrollFinalize->value]);
    $this->actingAs($finalizer)->postJson("/api/v1/payroll/periods/{$period->id}/finalize")->assertStatus(409);

    $resolver = User::factory()->create();
    wcGrant($resolver, [PermissionName::PayrollDisputeResolve->value]);
    $disputeId = $this->actingAs($resolver)->getJson('/api/v1/payroll/disputes')->json('data.0.id');

    $this->actingAs($resolver)
        ->postJson("/api/v1/payroll/disputes/{$disputeId}/resolve", ['resolution' => 'REJECTED', 'note' => 'No approved overtime for that day.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'RESOLVED');

    $this->actingAs($finalizer)->postJson("/api/v1/payroll/periods/{$period->id}/finalize")->assertOk();
});

test('a resolution without an explanation is rejected', function () {
    [$period, $entry] = wcReleasedPeriod();
    $employee = $entry->employee->user;
    wcGrant($employee, [PermissionName::PayslipViewSelf->value], Scope::Self);
    $this->actingAs($employee)->postJson("/api/v1/payroll/entries/{$entry->id}/dispute", ['reason' => 'x']);

    $resolver = User::factory()->create();
    wcGrant($resolver, [PermissionName::PayrollDisputeResolve->value]);
    $disputeId = $this->actingAs($resolver)->getJson('/api/v1/payroll/disputes')->json('data.0.id');

    $this->actingAs($resolver)
        ->postJson("/api/v1/payroll/disputes/{$disputeId}/resolve", ['resolution' => 'REJECTED'])
        ->assertStatus(422);
});

test('the payslip PDF is downloadable after finalisation', function () {
    [$period, $entry] = wcReleasedPeriod();
    $finalizer = User::factory()->create();
    wcGrant($finalizer, [PermissionName::PayrollView->value, PermissionName::PayrollFinalize->value]);
    $this->actingAs($finalizer)->postJson("/api/v1/payroll/periods/{$period->id}/finalize")->assertOk();

    $employee = $entry->employee->user;
    wcGrant($employee, [PermissionName::PayslipViewSelf->value], Scope::Self);

    $this->actingAs($employee)
        ->get("/api/v1/payroll/entries/{$entry->id}/payslip")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
