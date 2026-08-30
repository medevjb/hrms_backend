<?php

use App\Enums\OvertimeApprovalStage;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollArrearStatus;
use App\Enums\PayrollLineType;
use App\Enums\PayrollPeriodStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollArrear;
use App\Models\PayrollPeriod;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ArrearService;
use App\Services\OvertimeService;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §72/§146 — overtime approved after its period finalised
 * becomes an arrear, and the next payroll run pays it as its own line.
 */
beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    SalaryComponent::factory()->basic()->create();
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'payroll_cutoff_day' => null]);
});

function arrEmployee(): Employee
{
    $employee = Employee::factory()->create();
    $basicId = SalaryComponent::query()->where('code', 'BASIC')->value('id');
    app(SalaryService::class)->assign($employee, Carbon::parse('2026-01-01'), [$basicId => '30000'], null, User::factory()->create());

    return $employee->fresh();
}

function arrHrApprover(): User
{
    $user = User::factory()->create();
    $role = Role::query()->firstOrCreate(['name' => 'Head of HR']);
    $permission = Permission::query()->firstOrCreate(['name' => 'overtime.approve']);
    $role->permissions()->syncWithoutDetaching([$permission->id]);
    UserRole::factory()->create(['user_id' => $user->id, 'role_id' => $role->id]);

    return $user;
}

test('overtime approved after its period finalised opens an arrear', function () {
    $employee = arrEmployee();

    // finalise August
    $august = app(PayrollService::class)->createPeriod(2026, 8);
    app(PayrollService::class)->generate($august);
    app(PayrollWorkflowService::class)->release($august->fresh());
    app(PayrollWorkflowService::class)->finalize($august->fresh(), User::factory()->create());

    // now an August-dated overtime record is approved
    $attendance = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-24']);
    $record = OvertimeRecord::factory()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $attendance->id,
        'work_date' => '2026-08-24',
        'overtime_days' => 1,
        'status' => OvertimeStatus::PendingHr,
        'current_stage' => OvertimeApprovalStage::Hr,
    ]);

    app(OvertimeService::class)->approve($record, arrHrApprover());

    $arrear = PayrollArrear::query()->sole();
    expect($arrear->status)->toBe(PayrollArrearStatus::Pending)
        ->and($arrear->amount)->toBe('1000.0000') // 30000/30 daily * 1 day
        ->and($arrear->original_period_id)->toBe($august->id);
});

test('the next payroll run claims the arrear as its own line and marks it applied on finalise', function () {
    $employee = arrEmployee();

    PayrollArrear::factory()->create([
        'employee_id' => $employee->id,
        'amount' => '1500.0000',
        'original_period_id' => PayrollPeriod::factory()->status(PayrollPeriodStatus::Finalized),
    ]);

    $september = app(PayrollService::class)->createPeriod(2026, 9);
    app(PayrollService::class)->generate($september);

    $entry = $september->entries()->with('lines')->first();
    $arrearLine = $entry->lines->firstWhere('type', PayrollLineType::Arrear);

    expect($arrearLine)->not->toBeNull()
        ->and($arrearLine->amount)->toBe('1500.0000')
        ->and($entry->gross_earnings)->toBe('31500.0000');

    app(PayrollWorkflowService::class)->release($september->fresh());
    app(PayrollWorkflowService::class)->finalize($september->fresh(), User::factory()->create());

    expect(PayrollArrear::query()->first()->status)->toBe(PayrollArrearStatus::Applied);
});

test('a manual negative arrear becomes a recovery deduction', function () {
    $employee = arrEmployee();
    $closed = PayrollPeriod::factory()->status(PayrollPeriodStatus::Paid)->create();

    app(ArrearService::class)->openManualArrear(
        $employee, $closed, '-800.0000', 'Overpaid transport allowance', User::factory()->create(),
    );

    $october = app(PayrollService::class)->createPeriod(2026, 10);
    app(PayrollService::class)->generate($october);

    $entry = $october->entries()->with('lines')->first();
    $recovery = $entry->lines->firstWhere('type', PayrollLineType::ArrearRecovery);

    expect($recovery)->not->toBeNull()
        ->and($recovery->amount)->toBe('800.0000')
        ->and($entry->total_deductions)->toBe('800.0000')
        ->and($entry->net_salary)->toBe('29200.0000');
});
