<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveAccrualMode;
use App\Enums\LeaveBalanceTransactionType;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §144 — accrual, carry-forward/expiry at the leave-year
 * boundary, and every balance movement's audit trail. `leave_balances.balance`
 * is a cached sum; this is the only class allowed to write it, and it always
 * does so alongside the transaction row that justifies the change, so the
 * balance stays reconstructible by replaying leave_balance_transactions.
 */
class LeaveBalanceService
{
    /**
     * Which leave-year a calendar date falls in, expressed as the year it
     * started — e.g. a March 2026 date with a July start month belongs to
     * the leave year that started July 2025, so this returns 2025.
     */
    public function leaveYearFor(CarbonInterface $date, int $startMonth): int
    {
        return $date->month >= $startMonth ? $date->year : $date->year - 1;
    }

    public function leaveYearStart(int $leaveYear, int $startMonth): Carbon
    {
        return Carbon::create($leaveYear, $startMonth, 1)->startOfDay();
    }

    /**
     * The employee's balance row for this type/year, lazily created (and
     * back-filled with every accrual it should already have as of now) the
     * first time anything asks for it — a leave request, a balance screen,
     * or the scheduled jobs below.
     */
    public function balanceFor(Employee $employee, LeaveType $leaveType, int $leaveYear): LeaveBalance
    {
        $existing = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('leave_year', $leaveYear)
            ->first();

        $settings = OrganizationSettings::current();
        $yearStart = $this->leaveYearStart($leaveYear, $settings->leave_year_start_month);

        $balance = $existing ?? LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_year' => $leaveYear,
            'balance' => 0,
        ]);

        $this->accrueThroughToday($balance, $employee, $leaveType, $yearStart);

        return $balance->fresh();
    }

    /**
     * §144 — UPFRONT lands the full (or, for a mid-year joiner, prorated)
     * allocation on the leave-year start date. MONTHLY credits 1/12 on the
     * first of each month; this back-fills every month up to now so a
     * balance created mid-year (or queried for the first time) is correct
     * without waiting for the scheduled job to catch up.
     */
    private function accrueThroughToday(LeaveBalance $balance, Employee $employee, LeaveType $leaveType, Carbon $yearStart): void
    {
        $effectiveStart = $employee->joining_date->greaterThan($yearStart) ? $employee->joining_date : $yearStart;

        if ($leaveType->accrual_mode === LeaveAccrualMode::Upfront) {
            if ($this->hasTransaction($balance, 'opening')) {
                return;
            }

            $amount = $employee->joining_date->greaterThan($yearStart)
                ? $this->proratedAllocation($leaveType, $employee->joining_date, $yearStart)
                : (float) $leaveType->annual_allocation_days;

            if ($amount > 0) {
                $this->applyTransaction($balance, LeaveBalanceTransactionType::Accrual, $amount, note: 'opening');
            }

            return;
        }

        $monthly = round(((float) $leaveType->annual_allocation_days / 12) * 2) / 2;
        // A real (mutable) Carbon regardless of whether $effectiveStart is a
        // Carbon or a CarbonImmutable (employee->joining_date reads as the
        // latter) — addMonthNoOverflow() below needs to mutate in place.
        $cursor = Carbon::parse($effectiveStart->toDateString())->startOfMonth();
        $limit = Carbon::now()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($limit)) {
            $note = 'monthly:'.$cursor->format('Y-m');

            if (! $this->hasTransaction($balance, $note) && $monthly > 0) {
                $this->applyTransaction($balance, LeaveBalanceTransactionType::Accrual, $monthly, note: $note);
            }

            $cursor->addMonthNoOverflow();
        }
    }

    /**
     * §144 — "Allocation = annual allocation × (remaining months ÷ 12)",
     * rounded to the nearest 0.5 day. "Remaining months" is calendar
     * months touched by the joining month through the leave-year end —
     * §144's own worked example (2026-04-15 joiner, January leave year)
     * counts April through December as 9 remaining months, not the ~8.5
     * exact elapsed months between the two dates.
     */
    private function proratedAllocation(LeaveType $leaveType, CarbonInterface $joiningDate, CarbonInterface $yearStart): float
    {
        $yearEnd = $yearStart->copy()->addYear();
        $joiningMonthStart = Carbon::parse($joiningDate->toDateString())->startOfMonth();
        $remainingMonths = min(12, $joiningMonthStart->diffInMonths($yearEnd));
        $prorated = (float) $leaveType->annual_allocation_days * ($remainingMonths / 12);

        return round($prorated * 2) / 2;
    }

    /**
     * The scheduled leave-year rollover (§144): for every active employee
     * and leave type, either carry forward (capped) or expire the previous
     * year's unused balance, then open the new year — which also naturally
     * bootstraps anyone who joined during the previous year and never had
     * a balance row.
     */
    public function runYearRollover(int $newLeaveYear): void
    {
        $settings = OrganizationSettings::current();
        $previousYear = $newLeaveYear - 1;
        $rolloverNote = 'rollover:'.$newLeaveYear;

        $employees = Employee::query()
            ->whereIn('status', [EmployeeStatus::Active, EmployeeStatus::Probation, EmployeeStatus::NoticePeriod])
            ->get();
        $leaveTypes = LeaveType::query()->where('is_active', true)->get();

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                $previous = LeaveBalance::query()
                    ->where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('leave_year', $previousYear)
                    ->first();

                $carryAmount = 0.0;

                if ($previous !== null && ! $this->hasTransaction($previous, $rolloverNote) && (float) $previous->balance > 0) {
                    if ($leaveType->carry_forward_enabled) {
                        $cap = $leaveType->carry_forward_cap_days !== null
                            ? (float) $leaveType->carry_forward_cap_days
                            : $settings->leave_carry_forward_cap_days;

                        $carryAmount = $cap !== null ? min((float) $previous->balance, $cap) : (float) $previous->balance;
                    }

                    $expiring = (float) $previous->balance - $carryAmount;

                    if ($carryAmount > 0) {
                        $this->applyTransaction($previous, LeaveBalanceTransactionType::CarryForward, -$carryAmount, note: $rolloverNote);
                    }

                    if ($expiring > 0) {
                        $this->applyTransaction($previous, LeaveBalanceTransactionType::Expiry, -$expiring, note: $rolloverNote);
                    }
                }

                $newBalance = $this->balanceFor($employee, $leaveType, $newLeaveYear);
                $carryInNote = 'carry-in:'.$newLeaveYear;

                if ($carryAmount > 0 && ! $this->hasTransaction($newBalance, $carryInNote)) {
                    $this->applyTransaction($newBalance, LeaveBalanceTransactionType::CarryForward, $carryAmount, note: $carryInNote);
                }
            }
        }
    }

    /**
     * §37/§39 — deducted only on final approval, never at submission; a
     * request still in flight has not "used" the balance yet.
     */
    public function debitForApproval(LeaveRequest $request): void
    {
        $settings = OrganizationSettings::current();
        $leaveYear = $this->leaveYearFor($request->start_date, $settings->leave_year_start_month);
        $balance = $this->balanceFor($request->employee, $request->leaveType, $leaveYear);

        $this->applyTransaction(
            $balance,
            LeaveBalanceTransactionType::Approval,
            -(float) $request->days_requested,
            request: $request,
        );
    }

    /**
     * §144 cancellation — credits the balance back. Callers only invoke
     * this for the future-dated portion of a cancelled request; a fully
     * past request has nothing left to refund.
     */
    public function creditForCancellation(LeaveRequest $request, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $settings = OrganizationSettings::current();
        $leaveYear = $this->leaveYearFor($request->start_date, $settings->leave_year_start_month);
        $balance = $this->balanceFor($request->employee, $request->leaveType, $leaveYear);

        $this->applyTransaction($balance, LeaveBalanceTransactionType::Cancellation, $amount, request: $request);
    }

    /**
     * §37 manual adjustment — always auditable, never a bare column write.
     */
    public function adjust(LeaveBalance $balance, float $amount, string $note, User $actor): LeaveBalance
    {
        $this->applyTransaction($balance, LeaveBalanceTransactionType::Adjustment, $amount, note: $note, actor: $actor);

        app(AuditLogger::class)->record(
            AuditAction::LeaveBalanceAdjusted, $balance,
            newData: ['amount' => $amount], reason: $note, actor: $actor,
        );

        return $balance->fresh();
    }

    private function applyTransaction(
        LeaveBalance $balance,
        LeaveBalanceTransactionType $type,
        float $amount,
        ?LeaveRequest $request = null,
        ?string $note = null,
        ?User $actor = null,
    ): void {
        $balance->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'leave_request_id' => $request?->id,
            'note' => $note,
            'created_by_user_id' => $actor?->id,
        ]);

        $balance->increment('balance', $amount);
    }

    private function hasTransaction(LeaveBalance $balance, string $note): bool
    {
        return $balance->transactions()->where('note', $note)->exists();
    }
}
