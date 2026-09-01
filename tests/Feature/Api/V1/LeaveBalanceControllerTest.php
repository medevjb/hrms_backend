<?php

use App\Enums\PermissionName;
use App\Enums\Scope;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;

function lbcUser(PermissionName ...$permissions): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create();

    foreach ($permissions as $permission) {
        $perm = Permission::query()->firstOrCreate(['name' => $permission->value]);
        $role->permissions()->syncWithoutDetaching([$perm->id]);
    }

    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id, 'scope' => Scope::AllEmployees]);

    return $user;
}

beforeEach(function () {
    Carbon::setTestNow('2026-03-01 09:00:00');
    OrganizationSettings::current()->update(['leave_year_start_month' => 1, 'leave_carry_forward_cap_days' => null]);
});

afterEach(fn () => Carbon::setTestNow());

test('a veteran with nothing taken reads back fully available', function () {
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    LeaveType::factory()->create(['name' => 'Annual Leave', 'annual_allocation_days' => 20, 'accrual_mode' => 'UPFRONT']);

    $response = $this->actingAs($employee->user)->getJson('/api/v1/leave-balances');

    $response->assertOk();
    $response->assertJsonPath('data.0.entitlement', 20)
        ->assertJsonPath('data.0.balance', 20)
        ->assertJsonPath('data.0.taken', 0);
});

test('a mid-year joiner is not shown phantom "taken" days', function () {
    // Joins 2026-03-01 — a January leave year prorates the opening to
    // 20 * 10/12 = 16.5 days. The old entitlement-minus-balance shortcut
    // would report 3.5 days "taken" before they had touched anything.
    $employee = Employee::factory()->create(['joining_date' => '2026-03-01']);
    LeaveType::factory()->create(['annual_allocation_days' => 20, 'accrual_mode' => 'UPFRONT']);

    $response = $this->actingAs($employee->user)->getJson('/api/v1/leave-balances');

    $response->assertOk()
        ->assertJsonPath('data.0.balance', 16.5)
        ->assertJsonPath('data.0.taken', 0)
        ->assertJsonPath('data.0.entitlement', 16.5);
});

test('bulk-adjust GRANT adds days to every active employee', function () {
    $a = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $b = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_allocation_days' => 10, 'accrual_mode' => 'UPFRONT']);

    $manager = lbcUser(PermissionName::LeavePolicyManage);

    $response = $this->actingAs($manager)->postJson('/api/v1/leave-balances/bulk-adjust', [
        'leave_type_id' => $type->id,
        'mode' => 'GRANT',
        'amount' => 3,
        'note' => 'Board-approved extra days for 2026',
    ]);

    $response->assertOk()->assertJsonPath('data.affected', 2);

    foreach ([$a, $b] as $employee) {
        $balance = $this->actingAs($employee->user)->getJson('/api/v1/leave-balances')->json('data.0.balance');
        expect((float) $balance)->toBe(13.0);
    }
});

test('bulk-adjust SET lands everyone on the same balance', function () {
    Employee::factory()->count(2)->create(['joining_date' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_allocation_days' => 10, 'accrual_mode' => 'UPFRONT']);
    $manager = lbcUser(PermissionName::LeavePolicyManage);

    $this->actingAs($manager)->postJson('/api/v1/leave-balances/bulk-adjust', [
        'leave_type_id' => $type->id, 'mode' => 'SET', 'amount' => 5, 'note' => 'Reset',
    ])->assertOk()->assertJsonPath('data.affected', 2);

    Employee::query()->get()->each(function (Employee $employee) {
        $balance = $this->actingAs($employee->user)->getJson('/api/v1/leave-balances')->json('data.0.balance');
        expect((float) $balance)->toBe(5.0);
    });
});

test('bulk-adjust REAPPLY_DEFAULT resets everyone to the type allocation', function () {
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_allocation_days' => 12, 'accrual_mode' => 'UPFRONT']);
    $manager = lbcUser(PermissionName::LeavePolicyManage);

    // Move the balance off its default first.
    $this->actingAs($manager)->postJson('/api/v1/leave-balances/bulk-adjust', [
        'leave_type_id' => $type->id, 'mode' => 'GRANT', 'amount' => -4, 'note' => 'clawback',
    ])->assertOk();

    $this->actingAs($manager)->postJson('/api/v1/leave-balances/bulk-adjust', [
        'leave_type_id' => $type->id, 'mode' => 'REAPPLY_DEFAULT', 'note' => 'back to policy',
    ])->assertOk();

    $balance = $this->actingAs($employee->user)->getJson('/api/v1/leave-balances')->json('data.0.balance');
    expect((float) $balance)->toBe(12.0);
});

test('bulk-adjust needs leave.policy.manage — leave.balance.adjust is not enough', function () {
    $type = LeaveType::factory()->create();
    $hr = lbcUser(PermissionName::LeaveBalanceAdjust);

    $this->actingAs($hr)->postJson('/api/v1/leave-balances/bulk-adjust', [
        'leave_type_id' => $type->id, 'mode' => 'GRANT', 'amount' => 1, 'note' => 'nope',
    ])->assertStatus(403);
});

test('bulk-adjust GRANT and SET require an amount', function () {
    $type = LeaveType::factory()->create();
    $manager = lbcUser(PermissionName::LeavePolicyManage);

    $this->actingAs($manager)->postJson('/api/v1/leave-balances/bulk-adjust', [
        'leave_type_id' => $type->id, 'mode' => 'SET', 'note' => 'x',
    ])->assertJsonValidationErrors('amount');
});
