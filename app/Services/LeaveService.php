<?php

namespace App\Services;

use App\Enums\HalfDayPeriod;
use App\Enums\LeaveApprovalDecision;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * §34–§41 leave request lifecycle: submission, the role-routed approval
 * chain, rejection, §40 direct approval, and §144 cancellation. Balance
 * arithmetic itself lives in LeaveBalanceService — this class only decides
 * *when* a debit/credit happens.
 */
class LeaveService
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    /**
     * §37 — weekends and holidays never consume balance; a half-day request
     * is always exactly 0.5 regardless of span, since §138 only supports a
     * half-day request against a single work day.
     */
    public function estimateDays(CarbonInterface $startDate, CarbonInterface $endDate, bool $isHalfDay): float
    {
        if ($isHalfDay) {
            return 0.5;
        }

        $settings = OrganizationSettings::current();
        $days = 0;
        // A real (mutable) Carbon regardless of whether $startDate is a
        // Carbon or a CarbonImmutable (an Eloquent-cast date reads as the
        // latter, since this app configures Date::use(CarbonImmutable::class))
        // — addDay() below needs to mutate in place, and isWeekend() is
        // typed against the mutable class specifically.
        $cursor = Carbon::parse($startDate->toDateString());

        while ($cursor->lessThanOrEqualTo($endDate)) {
            $isWeekend = $settings->isWeekend($cursor);
            $isHoliday = Holiday::query()->where('date', $cursor->toDateString())->where('active', true)->exists();

            if (! $isWeekend && ! $isHoliday) {
                $days++;
            }

            $cursor->addDay();
        }

        return (float) $days;
    }

    /**
     * @throws ValidationException
     */
    public function submit(
        Employee $employee,
        LeaveType $leaveType,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        bool $isHalfDay,
        ?HalfDayPeriod $halfDayPeriod,
        ?string $reason,
    ): LeaveRequest {
        if ($isHalfDay && ! $leaveType->supports_half_day) {
            throw ValidationException::withMessages(['is_half_day' => ["{$leaveType->name} does not support half-day requests."]]);
        }

        if ($isHalfDay && ! $startDate->equalTo($endDate)) {
            throw ValidationException::withMessages(['end_date' => ['A half-day request must have the same start and end date.']]);
        }

        if ($leaveType->min_employment_days !== null
            && $employee->joining_date->diffInDays(Carbon::now()) < $leaveType->min_employment_days) {
            throw ValidationException::withMessages(['leave_type_id' => ["{$leaveType->name} is not yet available — it unlocks after {$leaveType->min_employment_days} days of employment."]]);
        }

        $daysRequested = $this->estimateDays($startDate, $endDate, $isHalfDay);

        if ($daysRequested <= 0) {
            throw ValidationException::withMessages(['start_date' => ['This date range contains no working days.']]);
        }

        if ($leaveType->max_consecutive_days !== null && $daysRequested > $leaveType->max_consecutive_days) {
            throw ValidationException::withMessages(['end_date' => ["{$leaveType->name} allows at most {$leaveType->max_consecutive_days} consecutive days."]]);
        }

        $this->assertNoOverlap($employee, $startDate, $endDate);

        $settings = OrganizationSettings::current();
        $leaveYear = $this->balances->leaveYearFor($startDate, $settings->leave_year_start_month);
        $balance = $this->balances->balanceFor($employee, $leaveType, $leaveYear);

        if ((float) $balance->balance < $daysRequested) {
            throw ValidationException::withMessages(['leave_type_id' => ["Insufficient {$leaveType->name} balance: {$balance->balance} available, {$daysRequested} requested."]]);
        }

        $requiredStages = $this->requiredStagesFor($employee);

        $request = LeaveRequest::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_half_day' => $isHalfDay,
            'half_day_period' => $halfDayPeriod,
            'days_requested' => $daysRequested,
            'reason' => $reason,
            'status' => LeaveStatus::Submitted,
            'current_stage' => LeaveApprovalStage::from($requiredStages[0]),
            'required_stages' => $requiredStages,
            'submitted_at' => Carbon::now(),
        ]);

        return $request->fresh();
    }

    public function approve(LeaveRequest $request, User $approver, ?string $reason = null): LeaveRequest
    {
        abort_unless($request->current_stage !== null, 409, 'This leave request has already been decided.');

        $stage = $request->current_stage;

        $request->approvals()->create([
            'stage' => $stage,
            'approver_user_id' => $approver->id,
            'decision' => LeaveApprovalDecision::Approved,
            'reason' => $reason,
            'decided_at' => Carbon::now(),
        ]);

        $index = array_search($stage->value, $request->required_stages, true);
        $isFinalStage = $index === count($request->required_stages) - 1;

        if ($isFinalStage) {
            $request->status = LeaveStatus::HrApproved;
            $request->current_stage = null;
            $request->decided_at = Carbon::now();
            $request->save();

            $this->balances->debitForApproval($request);

            return $request->fresh();
        }

        $request->status = match ($stage) {
            LeaveApprovalStage::TeamLeader => LeaveStatus::TeamLeaderApproved,
            LeaveApprovalStage::OperationManager => LeaveStatus::OperationManagerApproved,
            default => $request->status,
        };
        $request->current_stage = LeaveApprovalStage::from($request->required_stages[$index + 1]);
        $request->save();

        return $request->fresh();
    }

    public function reject(LeaveRequest $request, User $approver, string $reason): LeaveRequest
    {
        abort_unless($request->current_stage !== null, 409, 'This leave request has already been decided.');

        $request->approvals()->create([
            'stage' => $request->current_stage,
            'approver_user_id' => $approver->id,
            'decision' => LeaveApprovalDecision::Rejected,
            'reason' => $reason,
            'decided_at' => Carbon::now(),
        ]);

        $request->status = LeaveStatus::Rejected;
        $request->current_stage = null;
        $request->decided_at = Carbon::now();
        $request->rejection_reason = $reason;
        $request->rejected_by_user_id = $approver->id;
        $request->save();

        return $request->fresh();
    }

    /**
     * §40 — Admin/Head HR bypass whatever stages remain and approve
     * outright. The bypassed stages, the reason, the approver, and the
     * timestamp are all preserved for audit.
     */
    public function directApprove(LeaveRequest $request, User $approver, string $reason): LeaveRequest
    {
        abort_unless($request->current_stage !== null, 409, 'This leave request has already been decided.');

        $index = array_search($request->current_stage->value, $request->required_stages, true);
        $bypassedStages = $index === false ? [] : array_slice($request->required_stages, $index);

        $request->approvals()->create([
            'stage' => $request->current_stage,
            'approver_user_id' => $approver->id,
            'decision' => LeaveApprovalDecision::Approved,
            'reason' => $reason,
            'decided_at' => Carbon::now(),
        ]);

        $request->status = LeaveStatus::HrApproved;
        $request->current_stage = null;
        $request->decided_at = Carbon::now();
        $request->is_direct_approval = true;
        $request->direct_approval_reason = $reason;
        $request->bypassed_stages = $bypassedStages;
        $request->save();

        $this->balances->debitForApproval($request);

        return $request->fresh();
    }

    /**
     * §39/§144 — cancelling an approved request credits the balance back
     * and reverses ON_LEAVE attendance for future dates only; days already
     * lived through keep their attendance record as-is. A request that
     * never reached HR_APPROVED simply moves to CANCELLED with nothing to
     * refund, since no balance was ever debited.
     */
    public function cancel(LeaveRequest $request, User $actor): LeaveRequest
    {
        abort_if($request->status === LeaveStatus::Cancelled, 409, 'This leave request is already cancelled.');
        abort_if($request->status === LeaveStatus::Rejected, 409, 'A rejected leave request cannot be cancelled.');

        $wasApproved = $request->isApproved();

        $request->status = LeaveStatus::Cancelled;
        $request->current_stage = null;
        $request->cancelled_at = Carbon::now();
        $request->cancelled_by_user_id = $actor->id;
        $request->save();

        if ($wasApproved) {
            $refund = $this->futurePortionDays($request);
            $this->balances->creditForCancellation($request, $refund);
        }

        return $request->fresh();
    }

    /**
     * The fraction of an approved request's days_requested that falls on
     * or after today — the only portion cancellation ever refunds (§144).
     */
    private function futurePortionDays(LeaveRequest $request): float
    {
        $today = Carbon::today();

        if ($request->end_date->lessThan($today)) {
            return 0.0;
        }

        if ($request->is_half_day) {
            return $request->start_date->greaterThanOrEqualTo($today) ? (float) $request->days_requested : 0.0;
        }

        $futureStart = $request->start_date->greaterThanOrEqualTo($today) ? $request->start_date : $today;

        return $this->estimateDays($futureStart, $request->end_date, false);
    }

    private function assertNoOverlap(Employee $employee, CarbonInterface $startDate, CarbonInterface $endDate): void
    {
        $overlaps = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                LeaveStatus::Submitted, LeaveStatus::TeamLeaderApproved,
                LeaveStatus::OperationManagerApproved, LeaveStatus::HrApproved,
            ])
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => ['This date range overlaps an existing leave request.']]);
        }
    }

    /**
     * §41 — the requester's own role decides the chain. HR/HEAD_HR/ADMIN
     * stages are all "final approval" tiers (§38: "final approval still
     * belongs to HR/Head HR") — whichever one applies is the request's
     * only remaining stage, never chained after another of the three.
     * Admin's own flow isn't in §41's table; mirroring Head HR's row
     * (Head HR → Admin) the other direction is the most consistent
     * reading, so an Admin's leave still needs one peer-tier sign-off.
     *
     * @return list<string>
     */
    private function requiredStagesFor(Employee $employee): array
    {
        $user = $employee->user;

        $stages = match (true) {
            $user->hasRole('Admin') => [LeaveApprovalStage::HeadHr],
            $user->hasRole('Head of HR') => [LeaveApprovalStage::Admin],
            $user->hasRole('HR') => [LeaveApprovalStage::HeadHr],
            $user->hasRole('Operation Manager') => [LeaveApprovalStage::HeadHr],
            $user->hasRole('Team Leader') => [LeaveApprovalStage::OperationManager, LeaveApprovalStage::Hr],
            default => [LeaveApprovalStage::TeamLeader, LeaveApprovalStage::OperationManager, LeaveApprovalStage::Hr],
        };

        return array_map(fn (LeaveApprovalStage $stage) => $stage->value, $stages);
    }
}
