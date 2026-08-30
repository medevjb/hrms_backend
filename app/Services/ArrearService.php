<?php

namespace App\Services;

use App\Enums\PayrollArrearSourceType;
use App\Enums\PayrollArrearStatus;
use App\Models\Employee;
use App\Models\OvertimeRecord;
use App\Models\PayrollArrear;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * docs/PRD.md §72/§146 — arrears carry money from a closed period into the
 * next open one. Overtime approved after its period finalised is the
 * common case; a negative arrear recovers an overpayment.
 */
class ArrearService
{
    public function __construct(private readonly SalaryService $salaries) {}

    /**
     * §72 — the period that a date belongs to, if that period is closed
     * (FINALIZED / PAID / LOCKED). Null means the date is either in an open
     * period (which just recalculates) or in no period at all yet.
     */
    public function closedPeriodFor(Carbon $date): ?PayrollPeriod
    {
        $period = PayrollPeriod::query()
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->first();

        return $period !== null && $period->status->isClosed() ? $period : null;
    }

    /**
     * §72 — an overtime record was approved after its period had closed.
     * Value it at the daily salary that period used and file a PENDING
     * arrear for the next run to claim.
     */
    public function openOvertimeArrear(OvertimeRecord $record): ?PayrollArrear
    {
        $period = $this->closedPeriodFor(Carbon::parse($record->work_date->toDateString()));

        if ($period === null) {
            return null;
        }

        if (PayrollArrear::query()
            ->where('source_type', PayrollArrearSourceType::Overtime)
            ->where('source_id', $record->id)
            ->exists()) {
            return null;
        }

        $salary = $this->salaries->salaryOn($record->employee, $period->end_date);

        if ($salary === null) {
            return null;
        }

        $daily = $this->salaries->dailySalary($salary, $period);
        $amount = Money::round(Money::mul($daily, (string) $record->effectiveOvertimeDays()));

        return PayrollArrear::query()->create([
            'employee_id' => $record->employee_id,
            'source_type' => PayrollArrearSourceType::Overtime,
            'source_id' => $record->id,
            'original_period_id' => $period->id,
            'amount' => $amount,
            'reason' => "Overtime for {$record->work_date->toDateString()} approved after {$period->label} finalised.",
        ]);
    }

    /**
     * §146 — a manual arrear (positive top-up or negative recovery) against
     * a specific closed period.
     */
    public function openManualArrear(
        Employee $employee,
        PayrollPeriod $originalPeriod,
        string $amount,
        string $reason,
        User $actor,
    ): PayrollArrear {
        abort_unless($originalPeriod->status->isClosed(), 422, 'An arrear can only be raised against a closed period.');

        return PayrollArrear::query()->create([
            'employee_id' => $employee->id,
            'source_type' => PayrollArrearSourceType::Adjustment,
            'original_period_id' => $originalPeriod->id,
            'amount' => Money::round($amount),
            'reason' => $reason,
            'created_by_user_id' => $actor->id,
        ]);
    }

    /**
     * §146 — the next open period claims every unclaimed PENDING arrear for
     * an employee, so calculate() can render them as lines. Claiming only
     * points the arrear at the target period; it becomes APPLIED when that
     * period finalises.
     *
     * @return Collection<int, PayrollArrear>
     */
    public function claimFor(Employee $employee, PayrollPeriod $targetPeriod): Collection
    {
        PayrollArrear::query()
            ->where('employee_id', $employee->id)
            ->where('status', PayrollArrearStatus::Pending)
            ->whereNull('target_period_id')
            ->update(['target_period_id' => $targetPeriod->id]);

        return PayrollArrear::query()
            ->where('employee_id', $employee->id)
            ->where('target_period_id', $targetPeriod->id)
            ->whereIn('status', [PayrollArrearStatus::Pending, PayrollArrearStatus::Applied])
            ->get();
    }

    public function markApplied(PayrollPeriod $targetPeriod): void
    {
        PayrollArrear::query()
            ->where('target_period_id', $targetPeriod->id)
            ->where('status', PayrollArrearStatus::Pending)
            ->update(['status' => PayrollArrearStatus::Applied, 'applied_at' => Carbon::now()]);
    }
}
