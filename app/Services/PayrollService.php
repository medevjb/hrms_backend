<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LatePenaltyDeductionMode;
use App\Enums\LatePenaltyOutcome;
use App\Enums\LeaveStatus;
use App\Enums\PayrollEntryStatus;
use App\Enums\PayrollLineType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryComponentType;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LatePenaltyRule;
use App\Models\LeaveRequest;
use App\Models\OrganizationSettings;
use App\Models\PayrollAdjustment;
use App\Models\PayrollArrear;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSettings;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * docs/PRD.md §63–§69 — payroll period creation from the §63 cutoff config
 * and the §66 draft calculation:
 *
 *   base salary + allowances + overtime + bonus + manual earnings
 *   − late penalty − absence − unpaid leave − manual deductions
 *   = draft net salary
 *
 * Every line stores the inputs it was computed from (§141) so a disputed
 * payslip can be recomputed. Phase 9 owns review, release, disputes,
 * finalisation, payslip PDFs, and locking.
 */
class PayrollService
{
    public function __construct(
        private readonly SalaryService $salaries,
        private readonly OvertimeService $overtime,
        private readonly ArrearService $arrears,
    ) {}

    /**
     * §63/§64 — create the period for a calendar month, deriving its
     * boundaries from the organization's cutoff day and snapshotting the
     * cutoff day and salary-day method (§64).
     *
     * The payroll cutoff is an override: with none configured the period
     * falls back to the organization reporting month (§85), so a payroll
     * period covers exactly the same dates as the rest of the product.
     *
     * @throws ValidationException
     */
    public function createPeriod(int $year, int $month, ?User $actor = null): PayrollPeriod
    {
        $settings = OrganizationSettings::current();
        $cutoffDay = $settings->payroll_cutoff_day ?? $settings->reporting_month_cutoff_day;

        $period = app(ReportingPeriodService::class)->forKey(
            sprintf('%04d-%02d', $year, $month),
            $cutoffDay,
        );

        $start = $period->startDate;
        $end = $period->endDate;
        $label = $period->label;

        if (PayrollPeriod::query()->where('label', $label)->orWhere(fn ($q) => $q->where('start_date', $start->toDateString())->where('end_date', $end->toDateString()))->exists()) {
            throw ValidationException::withMessages(['label' => ["A payroll period for {$label} already exists."]]);
        }

        return PayrollPeriod::query()->create([
            'label' => $label,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'status' => PayrollPeriodStatus::Open,
            'cutoff_day_used' => $cutoffDay,
            'salary_day_calculation_method_used' => $settings->salary_day_calculation_method,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * §69 — "HR Generates Payroll → System Calculates Draft". Builds (or
     * refreshes) a payroll entry for every active employee with a salary
     * in force on the period end, then calculates each.
     *
     * @return array{entries: int}
     */
    public function generate(PayrollPeriod $period): array
    {
        abort_if($period->status->isClosed(), 409, 'This payroll period is closed.');

        $employees = Employee::query()
            ->whereIn('status', [EmployeeStatus::Active, EmployeeStatus::Probation, EmployeeStatus::NoticePeriod])
            ->get();

        DB::transaction(function () use ($period, $employees) {
            foreach ($employees as $employee) {
                if ($this->salaries->salaryOn($employee, $period->end_date) === null) {
                    continue;
                }

                $entry = PayrollEntry::query()->firstOrCreate([
                    'payroll_period_id' => $period->id,
                    'employee_id' => $employee->id,
                ]);

                $this->calculate($entry->fresh());
            }

            $period->update(['status' => PayrollPeriodStatus::Processing, 'processed_at' => Carbon::now()]);

            $this->recordRun($period);
        });

        return ['entries' => $period->entries()->count()];
    }

    /**
     * §69 — an audit row per draft calculation of a period, with the
     * totals it produced.
     */
    private function recordRun(PayrollPeriod $period): void
    {
        $entries = $period->entries()->get();

        PayrollRun::query()->create([
            'payroll_period_id' => $period->id,
            'sequence' => ($period->payrollRuns()->max('sequence') ?? 0) + 1,
            'entry_count' => $entries->count(),
            'gross_total' => Money::round(Money::sum($entries->pluck('gross_earnings'))),
            'deduction_total' => Money::round(Money::sum($entries->pluck('total_deductions'))),
            'net_total' => Money::round(Money::sum($entries->pluck('net_salary'))),
            'triggered_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * §66 — recompute one entry's lines and totals from scratch, keeping
     * the §68 manual adjustments (they regenerate their own lines).
     */
    public function calculate(PayrollEntry $entry): PayrollEntry
    {
        $period = $entry->period;
        $employee = $entry->employee;

        abort_unless($period->status->allowsRecalculation() || $period->status === PayrollPeriodStatus::Processing, 409, 'This payroll period can no longer be recalculated.');

        $salary = $this->salaries->salaryOn($employee, $period->end_date);
        abort_if($salary === null, 422, "No salary is on record for {$employee->fullName()} in this period.");

        $payrollSettings = PayrollSettings::current();
        $daily = $this->salaries->dailySalary($salary, $period);
        $divisorDays = $this->salaries->divisorDays($period);

        $lateDays = $this->lateDays($employee, $period);
        $absentDays = $this->absentDays($employee, $period);
        $unpaidLeaveDays = $this->unpaidLeaveDays($employee, $period);
        $overtimeDays = number_format(
            $this->overtime->approvedOvertimeDaysFor($employee, $period->start_date, $period->end_date),
            4, '.', '',
        );

        return DB::transaction(function () use (
            $entry, $salary, $daily, $divisorDays, $payrollSettings,
            $lateDays, $absentDays, $unpaidLeaveDays, $overtimeDays
        ) {
            $entry->lines()->delete();

            $earnings = [];
            $deductions = [];

            // §66 — base salary + allowances, straight from the salary version.
            foreach ($salary->components as $component) {
                $type = $component->component->type === SalaryComponentType::Basic
                    ? PayrollLineType::Basic
                    : PayrollLineType::Allowance;

                $line = $entry->lines()->create([
                    'category' => $type->category(),
                    'type' => $type,
                    'label' => $component->component->name,
                    'amount' => Money::round($component->amount),
                    'computed_from' => ['salary_component_id' => $component->salary_component_id],
                ]);
                $earnings[] = $line->amount;
            }

            // §67 — daily salary × approved overtime days.
            if ($payrollSettings->overtime_earnings_enabled && Money::compare($overtimeDays, '0') > 0) {
                $amount = Money::round(Money::mul($daily, $overtimeDays));
                $entry->lines()->create([
                    'category' => PayrollLineType::Overtime->category(),
                    'type' => PayrollLineType::Overtime,
                    'label' => 'Overtime',
                    'amount' => $amount,
                    'computed_from' => ['days' => $overtimeDays, 'daily_salary' => $daily],
                ]);
                $earnings[] = $amount;
            }

            // §61 — late penalty from the active policy tier.
            if ($payrollSettings->late_penalty_enabled) {
                $penalty = $this->latePenalty($lateDays, $daily, $entry->period->end_date);
                if ($penalty !== null) {
                    $entry->lines()->create([
                        'category' => PayrollLineType::LatePenalty->category(),
                        'type' => PayrollLineType::LatePenalty,
                        'label' => 'Late penalty',
                        'amount' => $penalty['amount'],
                        'computed_from' => $penalty['computed_from'],
                    ]);
                    $deductions[] = $penalty['amount'];
                }
            }

            // §65/§66 — unauthorised absence.
            if ($payrollSettings->absence_deduction_enabled && Money::compare($absentDays, '0') > 0) {
                $amount = Money::round(Money::mul($daily, $absentDays));
                $entry->lines()->create([
                    'category' => PayrollLineType::Absence->category(),
                    'type' => PayrollLineType::Absence,
                    'label' => 'Unauthorised absence',
                    'amount' => $amount,
                    'computed_from' => ['days' => $absentDays, 'daily_salary' => $daily],
                ]);
                $deductions[] = $amount;
            }

            // §66 — unpaid leave.
            if ($payrollSettings->unpaid_leave_deduction_enabled && Money::compare($unpaidLeaveDays, '0') > 0) {
                $amount = Money::round(Money::mul($daily, $unpaidLeaveDays));
                $entry->lines()->create([
                    'category' => PayrollLineType::UnpaidLeave->category(),
                    'type' => PayrollLineType::UnpaidLeave,
                    'label' => 'Unpaid leave',
                    'amount' => $amount,
                    'computed_from' => ['days' => $unpaidLeaveDays, 'daily_salary' => $daily],
                ]);
                $deductions[] = $amount;
            }

            // §68 — manual adjustments regenerate their own lines.
            foreach ($entry->adjustments as $adjustment) {
                $lineType = $adjustment->type->lineType();
                $line = $entry->lines()->create([
                    'category' => $lineType->category(),
                    'type' => $lineType,
                    'label' => $adjustment->label,
                    'amount' => Money::round($adjustment->amount),
                    'computed_from' => ['reason' => $adjustment->reason],
                    'source_type' => PayrollAdjustment::class,
                    'source_id' => $adjustment->id,
                    'is_manual' => true,
                ]);

                if ($lineType->category()->value === 'EARNING') {
                    $earnings[] = $line->amount;
                } else {
                    $deductions[] = $line->amount;
                }
            }

            // §146 — arrears claimed from closed periods. A positive arrear
            // is an earning line; a negative one is a recovery deduction.
            foreach ($this->arrears->claimFor($entry->employee, $entry->period) as $arrear) {
                $isRecovery = Money::isNegative($arrear->amount);
                $lineType = $isRecovery ? PayrollLineType::ArrearRecovery : PayrollLineType::Arrear;
                $absolute = $isRecovery ? Money::mul($arrear->amount, '-1') : (string) $arrear->amount;

                $line = $entry->lines()->create([
                    'category' => $lineType->category(),
                    'type' => $lineType,
                    'label' => "Arrear ({$arrear->originalPeriod->label})",
                    'amount' => Money::round($absolute),
                    'computed_from' => ['reason' => $arrear->reason, 'source' => $arrear->source_type->value],
                    'source_type' => PayrollArrear::class,
                    'source_id' => $arrear->id,
                ]);

                if ($isRecovery) {
                    $deductions[] = $line->amount;
                } else {
                    $earnings[] = $line->amount;
                }
            }

            $gross = Money::round(Money::sum($earnings));
            $totalDeductions = Money::round(Money::sum($deductions));
            $net = Money::round(Money::sub($gross, $totalDeductions));

            $entry->update([
                'employee_salary_id' => $salary->id,
                'status' => PayrollEntryStatus::Calculated,
                'basic_salary' => Money::round($salary->basic_salary),
                'daily_salary' => $daily,
                'period_days' => $divisorDays,
                'late_days' => $lateDays,
                'absent_days' => $absentDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'overtime_days' => $overtimeDays,
                'gross_earnings' => $gross,
                'total_deductions' => $totalDeductions,
                'net_salary' => $net,
                'calculated_at' => Carbon::now(),
            ]);

            return $entry->fresh(['lines', 'adjustments']);
        });
    }

    /**
     * §68 — record a manual adjustment and recalculate the entry.
     */
    public function adjust(PayrollEntry $entry, PayrollAdjustment $adjustment): PayrollEntry
    {
        abort_if($entry->period->status->isClosed(), 409, 'This payroll period is closed.');

        return $this->calculate($entry->fresh());
    }

    private function lateDays(Employee $employee, PayrollPeriod $period): string
    {
        $count = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceStatus::Late)
            ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->count();

        return number_format($count, 2, '.', '');
    }

    private function absentDays(Employee $employee, PayrollPeriod $period): string
    {
        $count = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('status', AttendanceStatus::Absent)
            ->whereBetween('work_date', [$period->start_date->toDateString(), $period->end_date->toDateString()])
            ->count();

        return number_format($count, 2, '.', '');
    }

    /**
     * §66 — approved unpaid-leave days whose span overlaps the period. V1
     * counts the whole request's days_requested when it overlaps at all;
     * splitting a request across a period boundary is a Phase 9 refinement.
     */
    private function unpaidLeaveDays(Employee $employee, PayrollPeriod $period): string
    {
        $days = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveStatus::HrApproved)
            ->whereHas('leaveType', fn ($q) => $q->where('is_paid', false))
            ->where('start_date', '<=', $period->end_date->toDateString())
            ->where('end_date', '>=', $period->start_date->toDateString())
            ->get()
            ->reduce(fn (string $carry, LeaveRequest $request) => Money::add($carry, (string) $request->days_requested), Money::ZERO);

        return number_format((float) $days, 2, '.', '');
    }

    /**
     * §61/§62 — the newest late-penalty policy version effective on or
     * before the period end, then the highest tier the late count reaches.
     *
     * @return array{amount: string, computed_from: array<string, mixed>}|null
     */
    private function latePenalty(string $lateDays, string $daily, CarbonInterface $periodEnd): ?array
    {
        $latestVersion = LatePenaltyRule::query()
            ->whereDate('effective_from', '<=', $periodEnd->toDateString())
            ->max('effective_from');

        if ($latestVersion === null) {
            return null;
        }

        $tier = LatePenaltyRule::query()
            ->whereDate('effective_from', $latestVersion)
            ->where('late_days_threshold', '<=', (int) $lateDays)
            ->orderByDesc('late_days_threshold')
            ->first();

        if ($tier === null || $tier->outcome === LatePenaltyOutcome::Warning) {
            return null;
        }

        $amount = $tier->deduction_mode === LatePenaltyDeductionMode::DayFraction
            ? Money::round(Money::mul($daily, (string) $tier->deduction_value))
            : Money::round((string) $tier->deduction_value);

        return [
            'amount' => $amount,
            'computed_from' => [
                'late_days' => $lateDays,
                'tier_threshold' => $tier->late_days_threshold,
                'mode' => $tier->deduction_mode?->value,
                'value' => (string) $tier->deduction_value,
            ],
        ];
    }
}
