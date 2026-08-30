<?php

use App\Enums\AttendanceStatus;
use App\Enums\LatePenaltyDeductionMode;
use App\Enums\LatePenaltyOutcome;
use App\Enums\LeaveStatus;
use App\Enums\PayrollAdjustmentType;
use App\Enums\PayrollEntryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LatePenaltyRule;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollAdjustment;
use App\Models\PayrollSettings;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * docs/PRD.md §118/§123 — one payroll period through every input and every
 * stage: salary + attendance + late rules + leave + overtime + a manual
 * adjustment → draft → review → release → confirm → finalise → payslip.
 */
beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    SalaryComponent::factory()->basic()->create();
    SalaryComponent::factory()->create(['code' => 'HOUSING', 'name' => 'Housing Allowance']);
    OrganizationSettings::current()->update(['timezone' => 'UTC']);
    PayrollSettings::current()->update([
        'late_penalty_enabled' => true,
        'absence_deduction_enabled' => true,
        'unpaid_leave_deduction_enabled' => true,
        'overtime_earnings_enabled' => true,
    ]);
    LatePenaltyRule::factory()->create([
        'effective_from' => '2026-01-01',
        'late_days_threshold' => 3,
        'outcome' => LatePenaltyOutcome::Deduction,
        'deduction_mode' => LatePenaltyDeductionMode::DayFraction,
        'deduction_value' => '1.0',
    ]);
});

function e2eEmployee(): Employee
{
    $employee = Employee::factory()->create();
    $components = SalaryComponent::query()->get()->keyBy('code');
    app(SalaryService::class)->assign(
        $employee,
        Carbon::parse('2026-01-01'),
        [$components['BASIC']->id => '27000', $components['HOUSING']->id => '3000'],
        null,
        User::factory()->create(),
    );

    return $employee->fresh();
}

test('a standard month period runs the whole flow with every input applied', function () {
    OrganizationSettings::current()->update(['payroll_cutoff_day' => null]);
    $employee = e2eEmployee();

    // 3 late days -> 1 full-day penalty (daily = 30000/30 = 1000)
    foreach (['2026-08-04', '2026-08-05', '2026-08-06'] as $date) {
        AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => $date, 'status' => AttendanceStatus::Late]);
    }
    // 1 unauthorised absence
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-11', 'status' => AttendanceStatus::Absent]);
    // 2 unpaid leave days
    $unpaid = LeaveType::factory()->create(['is_paid' => false, 'code' => 'UNPAID']);
    LeaveRequest::factory()->create([
        'employee_id' => $employee->id, 'leave_type_id' => $unpaid->id,
        'status' => LeaveStatus::HrApproved, 'current_stage' => null,
        'start_date' => '2026-08-18', 'end_date' => '2026-08-19', 'days_requested' => 2,
    ]);
    // 1 approved weekend overtime day
    $attendance = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-22']);
    OvertimeRecord::factory()->approved()->create([
        'employee_id' => $employee->id, 'attendance_record_id' => $attendance->id,
        'work_date' => '2026-08-22', 'overtime_days' => 1,
    ]);

    $period = app(PayrollService::class)->createPeriod(2026, 8);
    app(PayrollService::class)->generate($period);

    $entry = $period->entries()->with('lines')->first();

    // gross = 30000 base + 1000 overtime = 31000
    // deductions = 1000 late penalty + 1000 absence + 2000 unpaid leave = 4000
    expect($entry->gross_earnings)->toBe('31000.0000')
        ->and($entry->total_deductions)->toBe('4000.0000')
        ->and($entry->net_salary)->toBe('27000.0000')
        ->and($entry->late_days)->toBe('3.00');

    // HR adds a manual bonus, entry recalculates
    $adjustment = PayrollAdjustment::query()->create([
        'payroll_entry_id' => $entry->id, 'type' => PayrollAdjustmentType::Bonus,
        'label' => 'Retention bonus', 'amount' => '5000.0000',
        'reason' => 'Q3 retention', 'created_by_user_id' => User::factory()->create()->id,
    ]);
    app(PayrollService::class)->adjust($entry, $adjustment);
    expect($entry->fresh()->net_salary)->toBe('32000.0000');

    // review -> release -> employee confirms -> finalise
    $workflow = app(PayrollWorkflowService::class);
    $workflow->moveToReview($period->fresh());
    $workflow->release($period->fresh());

    $entry->refresh();
    expect($entry->status)->toBe(PayrollEntryStatus::Released);
    $workflow->acknowledge($entry, $employee->user);

    $workflow->finalize($period->fresh(), User::factory()->create());

    $period->refresh();
    $entry = $period->entries()->with('payslip')->first();
    expect($period->status)->toBe(PayrollPeriodStatus::Finalized)
        ->and($entry->status)->toBe(PayrollEntryStatus::Finalized)
        ->and($entry->payslip)->not->toBeNull()
        ->and($entry->payslip->net_salary)->toBe('32000.0000');
    Storage::disk('local')->assertExists($entry->payslip->file_path);

    // the overtime that fed this period is now marked processed (§72)
    expect(OvertimeRecord::query()->first()->status->value)->toBe('PAYROLL_PROCESSED');
});

test('a 26th-to-25th cutoff period covers the right date window', function () {
    OrganizationSettings::current()->update(['payroll_cutoff_day' => 25]);
    $employee = e2eEmployee();

    // a late day inside the window, one outside it
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-10', 'status' => AttendanceStatus::Absent]);
    AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-07-20', 'status' => AttendanceStatus::Absent]);

    $period = app(PayrollService::class)->createPeriod(2026, 8);
    expect($period->start_date->toDateString())->toBe('2026-07-26')
        ->and($period->end_date->toDateString())->toBe('2026-08-25');

    app(PayrollService::class)->generate($period);
    $entry = $period->entries()->first();

    // only the 2026-08-10 absence falls in 26 Jul – 25 Aug
    expect($entry->absent_days)->toBe('1.00');
});
