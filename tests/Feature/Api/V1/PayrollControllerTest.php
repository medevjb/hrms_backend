<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\UserRole;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §12/§59/§63–§69, §91 — salary, payroll periods, entries, and
 * the late-penalty policy over the API.
 */
function payGrant(User $user, array $permissions, Scope $scope = Scope::AllEmployees, ?int $scopeId = null): void
{
    $role = Role::query()->firstOrCreate(['name' => 'Payroll '.fake()->unique()->word()]);

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => $scope, 'scope_id' => $scopeId]);
}

function payComponents(): void
{
    SalaryComponent::factory()->basic()->create();
    SalaryComponent::factory()->create(['code' => 'HOUSING', 'name' => 'Housing Allowance']);
}

function paySetSalary(Employee $employee, string $basic = '30000'): void
{
    $basicId = SalaryComponent::query()->where('code', 'BASIC')->value('id');
    app(SalaryService::class)->assign($employee, Carbon::parse('2026-01-01'), [$basicId => $basic], null, User::factory()->create());
}

test('setting a salary needs employee.financial.manage; reading needs financial.view or being the employee', function () {
    payComponents();
    $employee = Employee::factory()->create();
    $basicId = SalaryComponent::query()->where('code', 'BASIC')->value('id');

    $viewer = User::factory()->create();
    payGrant($viewer, [PermissionName::EmployeeFinancialView->value]);

    // viewer can read, cannot write
    $this->actingAs($viewer)->getJson("/api/v1/employees/{$employee->id}/salary")->assertOk();
    $this->actingAs($viewer)->putJson("/api/v1/employees/{$employee->id}/salary", [
        'effective_from' => '2026-02-01',
        'components' => [['salary_component_id' => $basicId, 'amount' => '30000']],
    ])->assertStatus(403);

    $manager = User::factory()->create();
    payGrant($manager, [PermissionName::EmployeeFinancialView->value, PermissionName::EmployeeFinancialManage->value]);

    $this->actingAs($manager)->putJson("/api/v1/employees/{$employee->id}/salary", [
        'effective_from' => '2026-02-01',
        'components' => [['salary_component_id' => $basicId, 'amount' => '30000']],
    ])->assertStatus(201)->assertJsonPath('data.basic_salary', '30000.0000');
});

test('an employee with no financial permission still sees their own salary but not others', function () {
    payComponents();
    $employee = Employee::factory()->create();
    paySetSalary($employee);
    $other = Employee::factory()->create();
    paySetSalary($other);

    $this->actingAs($employee->user)->getJson("/api/v1/employees/{$employee->id}/salary")->assertOk();
    $this->actingAs($employee->user)->getJson("/api/v1/employees/{$other->id}/salary")->assertStatus(404);
});

test('creating and generating a payroll period is gated on payroll.prepare', function () {
    payComponents();
    $employee = Employee::factory()->create();
    paySetSalary($employee);

    $viewer = User::factory()->create();
    payGrant($viewer, [PermissionName::PayrollView->value]);
    $this->actingAs($viewer)->postJson('/api/v1/payroll/periods', ['year' => 2026, 'month' => 8])->assertStatus(403);

    $preparer = User::factory()->create();
    payGrant($preparer, [PermissionName::PayrollView->value, PermissionName::PayrollPrepare->value]);

    $create = $this->actingAs($preparer)->postJson('/api/v1/payroll/periods', ['year' => 2026, 'month' => 8]);
    $create->assertStatus(201)->assertJsonPath('data.label', 'August 2026');
    $periodId = $create->json('data.id');

    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/generate")
        ->assertOk()
        ->assertJsonPath('meta.entries', 1)
        ->assertJsonPath('data.status', 'PROCESSING');
});

test('an employee sees only their own payroll entry', function () {
    payComponents();
    $preparer = User::factory()->create();
    payGrant($preparer, [PermissionName::PayrollView->value, PermissionName::PayrollPrepare->value]);

    $mine = Employee::factory()->create();
    paySetSalary($mine);
    payGrant($mine->user, [PermissionName::PayslipViewSelf->value], Scope::Self);
    $theirs = Employee::factory()->create();
    paySetSalary($theirs);

    $periodId = $this->actingAs($preparer)->postJson('/api/v1/payroll/periods', ['year' => 2026, 'month' => 8])->json('data.id');
    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/generate");

    $this->actingAs($mine->user)->getJson('/api/v1/payroll/entries')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.employee.id', $mine->id);
});

test('a manual adjustment needs payroll.adjust and recalculates the entry', function () {
    payComponents();
    $preparer = User::factory()->create();
    payGrant($preparer, [PermissionName::PayrollView->value, PermissionName::PayrollPrepare->value, PermissionName::PayrollAdjust->value]);

    $employee = Employee::factory()->create();
    paySetSalary($employee, '30000');

    $periodId = $this->actingAs($preparer)->postJson('/api/v1/payroll/periods', ['year' => 2026, 'month' => 8])->json('data.id');
    $this->actingAs($preparer)->postJson("/api/v1/payroll/periods/{$periodId}/generate");
    $entryId = $this->actingAs($preparer)->getJson("/api/v1/payroll/entries?filter[payroll_period_id]={$periodId}")->json('data.0.id');

    $this->actingAs($employee->user)
        ->postJson("/api/v1/payroll/entries/{$entryId}/adjust", ['type' => 'BONUS', 'label' => 'x', 'amount' => '1000', 'reason' => 'y'])
        ->assertStatus(403);

    $this->actingAs($preparer)
        ->postJson("/api/v1/payroll/entries/{$entryId}/adjust", [
            'type' => 'BONUS', 'label' => 'Eid bonus', 'amount' => '5000', 'reason' => 'Festival bonus',
        ])
        ->assertOk()
        ->assertJsonPath('data.gross_earnings', '35000.0000')
        ->assertJsonPath('data.net_salary', '35000.0000');
});

test('the late-penalty policy is gated on payroll.settings.manage', function () {
    $user = User::factory()->create();
    payGrant($user, [PermissionName::PayrollSettingsManage->value]);

    $this->actingAs(User::factory()->create())->getJson('/api/v1/settings/late-penalty-rules')->assertStatus(403);

    $this->actingAs($user)->putJson('/api/v1/settings/late-penalty-rules', [
        'effective_from' => '2026-01-01',
        'tiers' => [
            ['late_days_threshold' => 3, 'outcome' => 'WARNING'],
            ['late_days_threshold' => 5, 'outcome' => 'DEDUCTION', 'deduction_mode' => 'DAY_FRACTION', 'deduction_value' => '0.5'],
        ],
    ])->assertOk()->assertJsonCount(2, 'data');

    $this->actingAs($user)->getJson('/api/v1/settings/late-penalty-rules')->assertOk()->assertJsonCount(2, 'data');
});

test('payroll settings expose the new toggles', function () {
    $user = User::factory()->create();
    payGrant($user, [PermissionName::PayrollSettingsManage->value]);

    $this->actingAs($user)->putJson('/api/v1/settings/payroll', [
        'payroll_cutoff_day' => 25,
        'late_penalty_enabled' => false,
        'dispute_window_days' => 10,
    ])->assertOk()
        ->assertJsonPath('data.payroll_cutoff_day', 25)
        ->assertJsonPath('data.late_penalty_enabled', false)
        ->assertJsonPath('data.dispute_window_days', 10);
});
