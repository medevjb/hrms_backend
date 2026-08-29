<?php

use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'leave_year_start_month' => 1, 'leave_carry_forward_cap_days' => null]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('an UPFRONT leave type credits the full annual allocation to a veteran employee on first access', function () {
    Carbon::setTestNow('2026-03-01');
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15, 'accrual_mode' => 'UPFRONT']);

    $balance = app(LeaveBalanceService::class)->balanceFor($employee, $leaveType, 2026);

    expect((float) $balance->balance)->toBe(15.0);
    expect($balance->transactions()->count())->toBe(1);
});

test('a mid-year joiner is prorated to the nearest 0.5 day (§144 worked example)', function () {
    Carbon::setTestNow('2026-06-01');
    $employee = Employee::factory()->create(['joining_date' => '2026-04-15']);
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15, 'accrual_mode' => 'UPFRONT']);

    $balance = app(LeaveBalanceService::class)->balanceFor($employee, $leaveType, 2026);

    // 15 * 9/12 = 11.25 -> rounds to 11.5, per §144's own worked example.
    expect((float) $balance->balance)->toBe(11.5);
});

test('a MONTHLY leave type only credits elapsed months', function () {
    Carbon::setTestNow('2026-04-01');
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 12, 'accrual_mode' => 'MONTHLY']);

    $balance = app(LeaveBalanceService::class)->balanceFor($employee, $leaveType, 2026);

    // Jan, Feb, Mar, Apr elapsed (Apr itself credits on its first day) = 4 * 1.0
    expect((float) $balance->balance)->toBe(4.0);

    Carbon::setTestNow('2026-04-01'); // re-querying the same month must not double-credit
    $balance = app(LeaveBalanceService::class)->balanceFor($employee, $leaveType, 2026);
    expect((float) $balance->balance)->toBe(4.0);
});

test('year rollover carries forward up to the cap and expires the rest', function () {
    Carbon::setTestNow('2026-12-31');
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $leaveType = LeaveType::factory()->create([
        'annual_allocation_days' => 15,
        'carry_forward_enabled' => true,
        'carry_forward_cap_days' => 5,
    ]);

    $service = app(LeaveBalanceService::class);
    $balance2026 = $service->balanceFor($employee, $leaveType, 2026);
    expect((float) $balance2026->balance)->toBe(15.0);

    Carbon::setTestNow('2027-01-01');
    $service->runYearRollover(2027);

    expect((float) $balance2026->fresh()->balance)->toBe(0.0);

    $balance2027 = $service->balanceFor($employee, $leaveType, 2027);
    // 15 opening + 5 carried in = 20
    expect((float) $balance2027->balance)->toBe(20.0);
});

test('year rollover expires everything when carry-forward is disabled', function () {
    Carbon::setTestNow('2026-12-31');
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15, 'carry_forward_enabled' => false]);

    $service = app(LeaveBalanceService::class);
    $service->balanceFor($employee, $leaveType, 2026);

    Carbon::setTestNow('2027-01-01');
    $service->runYearRollover(2027);

    $balance2027 = $service->balanceFor($employee, $leaveType, 2027);
    expect((float) $balance2027->balance)->toBe(15.0); // only the fresh opening allocation, nothing carried
});

test('running year rollover twice for the same year does not double-apply', function () {
    Carbon::setTestNow('2026-12-31');
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $leaveType = LeaveType::factory()->create([
        'annual_allocation_days' => 15,
        'carry_forward_enabled' => true,
    ]);

    $service = app(LeaveBalanceService::class);
    $service->balanceFor($employee, $leaveType, 2026);

    Carbon::setTestNow('2027-01-01');
    $service->runYearRollover(2027);
    $service->runYearRollover(2027);

    $balance2027 = $service->balanceFor($employee, $leaveType, 2027);
    expect((float) $balance2027->balance)->toBe(30.0); // 15 opening + 15 carried, exactly once
});

test('manual adjustment is auditable and moves the balance', function () {
    $employee = Employee::factory()->create(['joining_date' => '2020-01-01']);
    $leaveType = LeaveType::factory()->create(['annual_allocation_days' => 15]);
    $actor = User::factory()->create();

    $service = app(LeaveBalanceService::class);
    $balance = $service->balanceFor($employee, $leaveType, Carbon::now()->year);

    $service->adjust($balance, -2.5, 'Correcting a prior-system migration error', $actor);

    $balance->refresh();
    expect((float) $balance->balance)->toBe(12.5);

    $transaction = $balance->transactions()->latest('id')->first();
    expect($transaction->type->value)->toBe('ADJUSTMENT');
    expect($transaction->note)->toBe('Correcting a prior-system migration error');
    expect($transaction->created_by_user_id)->toBe($actor->id);
});
