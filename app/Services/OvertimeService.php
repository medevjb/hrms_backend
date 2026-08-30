<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeApprovalStage;
use App\Enums\OvertimeStatus;
use App\Enums\OvertimeType;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\User;
use App\Support\OvertimeDetectionSummary;
use App\Support\ShiftResolution;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * §42–§53 overtime: detection off finalised attendance (§52 — attendance
 * proves work, it doesn't authorise payment), the §50 TEAM_LEADER →
 * OPERATION_MANAGER → HR approval chain, and §68 manual grants. The money
 * (§67 — daily salary × approved days) is Phase 8's job; this class stops
 * at counting approved days (approvedOvertimeDaysFor()).
 */
class OvertimeService
{
    public function __construct(private readonly ShiftService $shifts) {}

    /**
     * Runs immediately after the nightly attendance close (§137) for the
     * same work_date, so WEEKEND/HOLIDAY status and worked_minutes are
     * already settled. Idempotent: an attendance day that already has an
     * OvertimeRecord is never revisited, so a re-run (or a manual
     * `attendance:close` of an old date) can't duplicate or overwrite a
     * record that's mid-approval.
     */
    public function detectForWorkDate(Carbon $workDate): OvertimeDetectionSummary
    {
        $settings = OrganizationSettings::current();

        if (! $settings->overtime_enabled) {
            return new OvertimeDetectionSummary(workDate: $workDate);
        }

        $employees = Employee::query()
            ->where('overtime_eligible', true)
            ->whereIn('status', [EmployeeStatus::Active, EmployeeStatus::Probation, EmployeeStatus::NoticePeriod])
            ->get();

        $threshold = $settings->overtime_full_day_minutes;
        $detected = 0;
        $rejected = 0;
        $skipped = 0;

        foreach ($employees as $employee) {
            $resolution = $this->shifts->resolveForDate($employee, $workDate);
            $type = $this->overtimeTypeFor($resolution, $settings);

            if ($type === null) {
                $skipped++;

                continue;
            }

            $record = $this->attendanceRecordFor($employee, $workDate);

            if ($record === null || $record->check_in === null || $record->check_out === null) {
                $skipped++;

                continue;
            }

            if (OvertimeRecord::query()->where('attendance_record_id', $record->id)->exists()) {
                $skipped++;

                continue;
            }

            $workedMinutes = $record->worked_minutes
                ?? (int) $record->check_in->diffInMinutes($record->check_out);

            if ($workedMinutes >= $threshold) {
                OvertimeRecord::query()->create([
                    'employee_id' => $employee->id,
                    'attendance_record_id' => $record->id,
                    'work_date' => $workDate->toDateString(),
                    'type' => $type,
                    'worked_minutes' => $workedMinutes,
                    'full_day_minutes_used' => $threshold,
                    'overtime_days' => 1,
                    'status' => OvertimeStatus::PendingTeamLeader,
                    'current_stage' => OvertimeApprovalStage::TeamLeader,
                ]);
                $detected++;

                continue;
            }

            // §46 — "Half-Day Overtime: OFF" for V1, so a sub-threshold day
            // earns zero. §107 open-question #7: surface it as
            // DETECTED → REJECTED, not hidden, so HR can still grant it
            // manually via §68 (adjust()).
            OvertimeRecord::query()->create([
                'employee_id' => $employee->id,
                'attendance_record_id' => $record->id,
                'work_date' => $workDate->toDateString(),
                'type' => $type,
                'worked_minutes' => $workedMinutes,
                'full_day_minutes_used' => $threshold,
                'overtime_days' => 0,
                'status' => OvertimeStatus::Rejected,
                'current_stage' => null,
                'rejection_reason' => "Insufficient working duration ({$workedMinutes}m of {$threshold}m required).",
                'decided_at' => Carbon::now(),
            ]);
            $rejected++;
        }

        return new OvertimeDetectionSummary(
            workDate: $workDate,
            detected: $detected,
            rejectedInsufficientDuration: $rejected,
            skipped: $skipped,
        );
    }

    /**
     * §50 — advance one stage, or, for an Admin/Head HR acting with
     * "exceptional authority", collapse whatever's left of the chain and
     * approve outright. Mirrors LeaveService::approve().
     */
    public function approve(OvertimeRecord $record, User $approver, ?string $reason = null): OvertimeRecord
    {
        abort_if($record->current_stage === null, 409, 'This overtime record has already been decided.');

        $stage = $record->current_stage;

        $record->approvals()->create([
            'stage' => $stage,
            'approver_user_id' => $approver->id,
            'decision' => OvertimeApprovalDecision::Approved,
            'reason' => $reason,
            'decided_at' => Carbon::now(),
        ]);

        if ($this->hasExceptionalAuthority($approver) || $stage === OvertimeApprovalStage::Hr) {
            $record->status = OvertimeStatus::Approved;
            $record->current_stage = null;
            $record->decided_at = Carbon::now();
            $record->save();

            app(AuditLogger::class)->record(AuditAction::OvertimeApproved, $record, reason: $reason, actor: $approver);

            // §72 — if this record's work_date belongs to a period that has
            // already finalised, the money can't be paid there; it becomes
            // an arrear the next run picks up.
            app(ArrearService::class)->openOvertimeArrear($record->fresh());

            return $record->fresh();
        }

        // The Hr stage is already handled above (it's the final stage), so
        // only the two advancing stages reach here.
        [$nextStage, $nextStatus] = match ($stage) {
            OvertimeApprovalStage::TeamLeader => [OvertimeApprovalStage::OperationManager, OvertimeStatus::PendingOperationManager],
            OvertimeApprovalStage::OperationManager => [OvertimeApprovalStage::Hr, OvertimeStatus::PendingHr],
        };

        $record->current_stage = $nextStage;
        $record->status = $nextStatus;
        $record->save();

        return $record->fresh();
    }

    public function reject(OvertimeRecord $record, User $approver, string $reason): OvertimeRecord
    {
        abort_if($record->current_stage === null, 409, 'This overtime record has already been decided.');

        $record->approvals()->create([
            'stage' => $record->current_stage,
            'approver_user_id' => $approver->id,
            'decision' => OvertimeApprovalDecision::Rejected,
            'reason' => $reason,
            'decided_at' => Carbon::now(),
        ]);

        $record->status = OvertimeStatus::Rejected;
        $record->current_stage = null;
        $record->decided_at = Carbon::now();
        $record->rejection_reason = $reason;
        $record->rejected_by_user_id = $approver->id;
        $record->save();

        app(AuditLogger::class)->record(AuditAction::OvertimeApproved, $record, newData: ['decision' => 'REJECTED'], reason: $reason, actor: $approver);

        return $record->fresh();
    }

    /**
     * §68 — an authorised HR user overrides the detected day count: grant a
     * sub-threshold day that detection auto-rejected, or trim/zero one. The
     * previous value, new value, reason, actor, and time are all kept
     * (override columns + audit fields). A grant (> 0 days) on a rejected
     * record also moves it to APPROVED — there's no chain to re-walk for a
     * discretionary HR grant.
     *
     * @throws ValidationException
     */
    public function adjust(OvertimeRecord $record, float $days, string $reason, User $actor): OvertimeRecord
    {
        abort_if(
            $record->status === OvertimeStatus::PayrollProcessed,
            409,
            'This overtime record is already in a finalised payroll run (§72).',
        );

        if ($days < 0) {
            throw ValidationException::withMessages(['overtime_days' => ['Overtime days cannot be negative.']]);
        }

        $record->fill([
            'manual_days_override' => $days,
            'manual_adjustment_reason' => $reason,
            'adjusted_by_user_id' => $actor->id,
            'adjusted_at' => Carbon::now(),
        ]);

        $becameApproved = $days > 0 && $record->status === OvertimeStatus::Rejected;

        if ($becameApproved) {
            $record->status = OvertimeStatus::Approved;
            $record->current_stage = null;
            $record->decided_at = Carbon::now();
            $record->rejection_reason = null;
            $record->rejected_by_user_id = null;
        }

        $record->save();

        app(AuditLogger::class)->record(
            AuditAction::OvertimeAdjusted, $record,
            newData: ['manual_days_override' => (string) $days], reason: $reason, actor: $actor,
        );

        if ($becameApproved) {
            app(ArrearService::class)->openOvertimeArrear($record->fresh());
        }

        return $record->fresh();
    }

    /**
     * §67 seam for Phase 8 — the approved overtime-day total for an
     * employee over a payroll period. Reads effectiveOvertimeDays() so an
     * HR §68 grant counts; PAYROLL_PROCESSED days are excluded because
     * they've already been paid in an earlier run (§72 arrears handle the
     * late-approval case).
     */
    public function approvedOvertimeDaysFor(Employee $employee, CarbonInterface $from, CarbonInterface $to): float
    {
        return OvertimeRecord::query()
            ->where('employee_id', $employee->id)
            ->where('status', OvertimeStatus::Approved)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->sum(fn (OvertimeRecord $record) => $record->effectiveOvertimeDays());
    }

    private function overtimeTypeFor(ShiftResolution $resolution, OrganizationSettings $settings): ?OvertimeType
    {
        if ($resolution->isHoliday && $settings->holiday_overtime_enabled) {
            return OvertimeType::Holiday;
        }

        if ($resolution->isWeekend && $settings->weekend_overtime_enabled) {
            return OvertimeType::Weekend;
        }

        return null;
    }

    private function attendanceRecordFor(Employee $employee, Carbon $workDate): ?AttendanceRecord
    {
        return AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', $workDate->toDateString())
            ->first();
    }

    private function hasExceptionalAuthority(User $user): bool
    {
        return $user->hasRole('Admin') || $user->hasRole('Head of HR');
    }
}
