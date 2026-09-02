<?php

use App\Enums\AttendanceStatus;
use App\Enums\LatePenaltyDeductionMode;
use App\Enums\LatePenaltyOutcome;
use App\Enums\LeaveStatus;
use App\Enums\PayrollAdjustmentType;
use App\Enums\PayrollPeriodStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LatePenaltyRule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollAdjustment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollSettings;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * docs/PRD.md §63–§68 — payroll period creation from the §63 cutoff, and
 * the §66 draft calculation end to end.
 */
beforeEach(function () {
    SalaryComponent::factory()->basic()->create();
    SalaryComponent::factory()->create(['code' => 'HOUSING', 'name' => 'Housing Allowance']);
    // These cases assert plain calendar-month boundaries, so clear both the
    // payroll cutoff and the §85 reporting-month cutoff it falls back to.
    OrganizationSettings::current()->update([
        'timezone' => 'UTC',
        'payroll_cutoff_day' => null,
        'reporting_month_cutoff_day' => null,
    ]);
    PayrollSettings::current()->update([
        'late_penalty_enabled' => true,
        'absence_deduction_enabled' => true,
        'unpaid_leave_deduction_enabled' => true,
        'overtime_earnings_enabled' => true,
    ]);
});

function pcEmployeeWithSalary(string $basic = '30000', string $housing = '0'): Employee
{
    $employee = Employee::factory()->create();
    $components = SalaryComponent::query()->get()->keyBy('code');

    app(SalaryService::class)->assign(
        $employee,
        Carbon::parse('2026-01-01'),
        array_filter([
            $components['BASIC']->id => $basic,
            $components['HOUSING']->id => $housing,
        ]),
        null,
        User::factory()->create(),
    );

    return $employee->fresh();
}

function pcPeriod(): PayrollPeriod
{
    return app(PayrollService::class)->createPeriod(2026, 8);
}

function pcEntry(Employee $employee, PayrollPeriod $period): PayrollEntry
{
    $entry = PayrollEntry::query()->create([
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
    ]);

    return app(PayrollService::class)->calculate($entry);
}

test('a standard period runs the full calendar month', function () {
    $period = pcPeriod();

    expect($period->start_date->toDateString())->toBe('2026-08-01')
        ->and($period->end_date->toDateString())->toBe('2026-08-31')
        ->and($period->label)->toBe('August 2026')
        ->and($period->status)->toBe(PayrollPeriodStatus::Open);
});

test('a custom cutoff shifts the period boundaries', function () {
    OrganizationSettings::current()->update(['payroll_cutoff_day' => 25]);

    $period = app(PayrollService::class)->createPeriod(2026, 8);

    expect($period->start_date->toDateString())->toBe('2026-07-26')
        ->and($period->end_date->toDateString())->toBe('2026-08-25')
        ->and($period->cutoff_day_used)->toBe(25);
});

test('with no payroll cutoff the period falls back to the organization reporting month', function () {
    OrganizationSettings::current()->update([
        'payroll_cutoff_day' => null,
        'reporting_month_cutoff_day' => 25,
    ]);

    $period = app(PayrollService::class)->createPeriod(2026, 9);

    expect($period->start_date->toDateString())->toBe('2026-08-26')
        ->and($period->end_date->toDateString())->toBe('2026-09-25')
        ->and($period->label)->toBe('September 2026')
        ->and($period->cutoff_day_used)->toBe(25);
});

test('an explicit payroll cutoff still overrides the reporting month', function () {
    OrganizationSettings::current()->update([
        'payroll_cutoff_day' => 20,
        'reporting_month_cutoff_day' => 25,
    ]);

    $period = app(PayrollService::class)->createPeriod(2026, 9);

    expect($period->start_date->toDateString())->toBe('2026-08-21')
        ->and($period->end_date->toDateString())->toBe('2026-09-20')
        ->and($period->cutoff_day_used)->toBe(20);
});

test('base salary plus allowances is the gross, and net with no deductions equals gross', function () {
    $employee = pcEmployeeWithSalary('30000', '5000');
    $entry = pcEntry($employee, pcPeriod());

    expect($entry->gross_earnings)->toBe('35000.0000')
        ->and($entry->total_deductions)->toBe('0.0000')
        ->and($entry->net_salary)->toBe('35000.0000')
        ->and($entry->daily_salary)->toBe('1166.6667') // 35000 / 30
        ->and($entry->lines)->toHaveCount(2);
});

test('approved overtime days are paid at the daily rate', function () {
    $employee = pcEmployeeWithSalary('30000');
    $period = pcPeriod();

    $attendance = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-15']);
    OvertimeRecord::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $attendance->id,
        'work_date' => '2026-08-15',
        'overtime_days' => 2,
    ]);

    $entry = pcEntry($employee, $period);

    // daily 1000 * 2 days = 2000 overtime earning
    expect($entry->overtime_days)->toBe('2.00')
        ->and($entry->gross_earnings)->toBe('32000.0000')
        ->and($entry->net_salary)->toBe('32000.0000');
});

test('the late-penalty tier is picked by the qualified late count', function () {
    LatePenaltyRule::factory()->warning(3)->create(['effective_from' => '2026-01-01']);
    LatePenaltyRule::factory()->create([
        'effective_from' => '2026-01-01',
        'late_days_threshold' => 5,
        'outcome' => LatePenaltyOutcome::Deduction,
        'deduction_mode' => LatePenaltyDeductionMode::DayFraction,
        'deduction_value' => '0.5',
    ]);

    $employee = pcEmployeeWithSalary('30000');
    $period = pcPeriod();

    foreach (['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'] as $date) {
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Late,
        ]);
    }

    $entry = pcEntry($employee, $period);

    // 5 late days -> 0.5 day deduction -> 500
    expect($entry->late_days)->toBe('5.00')
        ->and($entry->total_deductions)->toBe('500.0000')
        ->and($entry->net_salary)->toBe('29500.0000');
});

test('unauthorised absence and unpaid leave are deducted at the daily rate', function () {
    $employee = pcEmployeeWithSalary('30000');
    $period = pcPeriod();

    AttendanceRecord::factory()->create([
        'employee_id' => $employee->id, 'work_date' => '2026-08-10', 'status' => AttendanceStatus::Absent,
    ]);

    $unpaidType = LeaveType::factory()->create(['is_paid' => false, 'code' => 'UNPAID']);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $unpaidType->id,
        'status' => LeaveStatus::HrApproved,
        'start_date' => '2026-08-20',
        'end_date' => '2026-08-21',
        'days_requested' => 2,
        'current_stage' => null,
    ]);

    $entry = pcEntry($employee, $period);

    // 1 absent day (1000) + 2 unpaid leave days (2000) = 3000
    expect($entry->absent_days)->toBe('1.00')
        ->and($entry->unpaid_leave_days)->toBe('2.00')
        ->and($entry->total_deductions)->toBe('3000.0000')
        ->and($entry->net_salary)->toBe('27000.0000');
});

test('a manual bonus adjustment adds an earning line and recalculates', function () {
    $employee = pcEmployeeWithSalary('30000');
    $period = pcPeriod();
    $entry = pcEntry($employee, $period);

    $adjustment = PayrollAdjustment::query()->create([
        'payroll_entry_id' => $entry->id,
        'type' => PayrollAdjustmentType::Bonus,
        'label' => 'Eid bonus',
        'amount' => '5000.0000',
        'reason' => 'Annual festival bonus',
        'created_by_user_id' => User::factory()->create()->id,
    ]);

    $recalculated = app(PayrollService::class)->adjust($entry, $adjustment);

    expect($recalculated->gross_earnings)->toBe('35000.0000')
        ->and($recalculated->net_salary)->toBe('35000.0000')
        ->and($recalculated->lines->where('is_manual', true))->toHaveCount(1);
});

test('generate builds an entry for every active employee with a salary', function () {
    pcEmployeeWithSalary('30000');
    pcEmployeeWithSalary('40000');
    Employee::factory()->create(); // no salary — skipped

    $period = pcPeriod();
    $result = app(PayrollService::class)->generate($period);

    expect($result['entries'])->toBe(2)
        ->and($period->fresh()->status)->toBe(PayrollPeriodStatus::Processing);
});

test('a closed period cannot be generated or recalculated', function () {
    $employee = pcEmployeeWithSalary('30000');
    $period = pcPeriod();
    $entry = pcEntry($employee, $period);
    $period->update(['status' => PayrollPeriodStatus::Finalized]);

    expect(fn () => app(PayrollService::class)->generate($period->fresh()))
        ->toThrow(HttpException::class);
    expect(fn () => app(PayrollService::class)->calculate($entry->fresh()))
        ->toThrow(HttpException::class);
});

test('creating a duplicate period is rejected', function () {
    pcPeriod();

    expect(fn () => pcPeriod())->toThrow(ValidationException::class);
});
