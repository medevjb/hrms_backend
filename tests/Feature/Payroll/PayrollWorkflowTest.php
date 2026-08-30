<?php

use App\Enums\OvertimeStatus;
use App\Enums\PayrollAcknowledgementStatus;
use App\Enums\PayrollDisputeResolution;
use App\Enums\PayrollDisputeStatus;
use App\Enums\PayrollEntryStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use App\Services\SalaryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * docs/PRD.md §69/§70/§72/§147 — the payroll period state machine and the
 * dispute lifecycle.
 */
beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    SalaryComponent::factory()->basic()->create();
    OrganizationSettings::current()->update(['timezone' => 'UTC', 'payroll_cutoff_day' => null]);
});

function wfEmployee(string $basic = '30000'): Employee
{
    $employee = Employee::factory()->create();
    $basicId = SalaryComponent::query()->where('code', 'BASIC')->value('id');
    app(SalaryService::class)->assign($employee, Carbon::parse('2026-01-01'), [$basicId => $basic], null, User::factory()->create());

    return $employee->fresh();
}

function wfGeneratedPeriod(): PayrollPeriod
{
    $period = app(PayrollService::class)->createPeriod(2026, 8);
    app(PayrollService::class)->generate($period);

    return $period->fresh();
}

function workflow(): PayrollWorkflowService
{
    return app(PayrollWorkflowService::class);
}

test('a period walks review, release, and finalisation', function () {
    wfEmployee();
    $period = wfGeneratedPeriod();

    workflow()->moveToReview($period);
    expect($period->fresh()->status)->toBe(PayrollPeriodStatus::Review);

    workflow()->release($period->fresh());
    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::EmployeeConfirmation)
        ->and($period->entries()->first()->status)->toBe(PayrollEntryStatus::Released)
        ->and($period->entries()->first()->acknowledgement_status)->toBe(PayrollAcknowledgementStatus::Pending);

    workflow()->finalize($period->fresh(), User::factory()->create());
    $period->refresh();
    expect($period->status)->toBe(PayrollPeriodStatus::Finalized)
        ->and($period->finalized_at)->not->toBeNull();

    $entry = $period->entries()->with('payslip')->first();
    expect($entry->status)->toBe(PayrollEntryStatus::Finalized)
        ->and($entry->payslip)->not->toBeNull();
    Storage::disk('local')->assertExists($entry->payslip->file_path);
});

test('finalisation marks the period overtime records processed', function () {
    $employee = wfEmployee();
    $period = wfGeneratedPeriod();

    $attendance = AttendanceRecord::factory()->create(['employee_id' => $employee->id, 'work_date' => '2026-08-16']);
    OvertimeRecord::factory()->approved()->create([
        'employee_id' => $employee->id,
        'attendance_record_id' => $attendance->id,
        'work_date' => '2026-08-16',
        'overtime_days' => 1,
    ]);

    workflow()->release($period->fresh());
    workflow()->finalize($period->fresh(), User::factory()->create());

    expect(OvertimeRecord::query()->first()->status)->toBe(OvertimeStatus::PayrollProcessed);
});

test('the employee acknowledges their payslip without changing a number', function () {
    $employee = wfEmployee();
    $period = wfGeneratedPeriod();
    workflow()->release($period->fresh());
    $entry = $period->entries()->first();
    $net = $entry->net_salary;

    $acknowledged = workflow()->acknowledge($entry->fresh(), $employee->user);

    expect($acknowledged->acknowledgement_status)->toBe(PayrollAcknowledgementStatus::Acknowledged)
        ->and($acknowledged->net_salary)->toBe($net);
});

test('a disputed entry blocks finalisation until it is resolved', function () {
    $employee = wfEmployee();
    $period = wfGeneratedPeriod();
    workflow()->release($period->fresh());
    $entry = $period->entries()->first();

    $dispute = workflow()->raiseDispute($entry->fresh(), $employee->user, 'My overtime is missing.');
    expect($entry->fresh()->acknowledgement_status)->toBe(PayrollAcknowledgementStatus::Disputed);

    expect(fn () => workflow()->finalize($period->fresh(), User::factory()->create()))
        ->toThrow(HttpException::class);

    workflow()->resolveDispute($dispute->fresh(), User::factory()->create(), PayrollDisputeResolution::Rejected, 'Overtime was never approved for that day.');

    expect($dispute->fresh()->status)->toBe(PayrollDisputeStatus::Resolved)
        ->and($entry->fresh()->acknowledgement_status)->toBe(PayrollAcknowledgementStatus::Resolved);

    workflow()->finalize($period->fresh(), User::factory()->create());
    expect($period->fresh()->status)->toBe(PayrollPeriodStatus::Finalized);
});

test('an upheld dispute returns the entry to pending for re-acknowledgement', function () {
    $employee = wfEmployee();
    $period = wfGeneratedPeriod();
    workflow()->release($period->fresh());
    $entry = $period->entries()->first();
    $dispute = workflow()->raiseDispute($entry->fresh(), $employee->user, 'Wrong amount.');

    workflow()->resolveDispute($dispute->fresh(), User::factory()->create(), PayrollDisputeResolution::Upheld, 'Correcting via adjustment.');

    expect($entry->fresh()->acknowledgement_status)->toBe(PayrollAcknowledgementStatus::Pending);
});

test('entries past the dispute window are auto-acknowledged at finalisation', function () {
    wfEmployee();
    $period = wfGeneratedPeriod();
    workflow()->release($period->fresh());
    $period->entries()->update(['released_at' => Carbon::now()->subDays(30)]);

    workflow()->finalize($period->fresh(), User::factory()->create());

    expect($period->entries()->first()->acknowledgement_status)->toBe(PayrollAcknowledgementStatus::AutoAcknowledged);
});

test('paid then locked are the terminal transitions', function () {
    wfEmployee();
    $period = wfGeneratedPeriod();
    workflow()->release($period->fresh());
    workflow()->finalize($period->fresh(), User::factory()->create());

    workflow()->markPaid($period->fresh());
    expect($period->fresh()->status)->toBe(PayrollPeriodStatus::Paid);

    workflow()->lock($period->fresh());
    expect($period->fresh()->status)->toBe(PayrollPeriodStatus::Locked);
});
