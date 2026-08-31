<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use App\Notifications\EmployeeInvitationNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Employees enter through an HR-initiated invite, never self-registration
 * (docs/PRD.md §92.4, §148 #2) — creating one creates its paired User in
 * the same call, with a password nobody knows, and emails a link to set
 * the real one.
 */
class EmployeeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function invite(array $data, User $invitedBy): Employee
    {
        $user = User::query()->create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'email' => $data['email'],
            'password' => Str::password(40),
        ]);

        $employee = $user->employee()->create([
            'employee_code' => $data['employee_code'] ?? $this->generateEmployeeCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'joining_date' => $data['joining_date'],
            'designation' => $data['designation'],
            'employment_type' => $data['employment_type'],
            'status' => EmployeeStatus::Invited,
            'confirmation_date' => $data['confirmation_date'] ?? null,
            'office_location' => $data['office_location'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'weekend_day' => $data['weekend_day'] ?? null,
            'overtime_eligible' => $data['overtime_eligible'] ?? true,
        ]);

        $this->recordStatusChange($employee, null, EmployeeStatus::Invited, 'Employee created', $invitedBy);

        $this->sendInvitation($user);

        return $employee;
    }

    /**
     * Re-issues the invitation link for someone who hasn't onboarded yet.
     * The original link is single-use and expires after 72h, and it shares
     * a token with the ordinary password-reset flow (docs/PRD.md §148 #2) —
     * so any of "expired", "already clicked", or "they hit forgot-password"
     * leaves HR with no way back in without this.
     */
    public function resendInvitation(Employee $employee): void
    {
        abort_unless(
            $employee->status === EmployeeStatus::Invited,
            409,
            'Only an employee who has not accepted their invitation can be re-invited.',
        );

        $user = $employee->user;

        abort_if($user === null, 409, 'This employee has no pending account to invite.');

        $this->sendInvitation($user);
    }

    private function sendInvitation(User $user): void
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker('employee_invitations');
        $user->notify(new EmployeeInvitationNotification($broker->createToken($user)));
    }

    /**
     * Permanently removes an employee and their paired user account. Only
     * ever allowed for an INVITED employee with no operational history —
     * a mistaken invite or a hire that fell through. Everyone else keeps
     * their records; use a status change (§13) instead.
     */
    public function delete(Employee $employee, User $actor): void
    {
        abort_unless(
            $employee->status === EmployeeStatus::Invited,
            409,
            'Only an invited employee who has not onboarded can be deleted. Archive or terminate an active employee instead.',
        );

        $blockers = [
            'attendance records' => $employee->attendanceRecords()->exists(),
            'leave requests' => $employee->leaveRequests()->exists(),
            'overtime records' => $employee->overtimeRecords()->exists(),
            'payroll entries' => $employee->payrollEntries()->exists(),
            'salary history' => $employee->salaries()->exists(),
            'documents' => $employee->documents()->exists(),
            'team memberships' => $employee->teamMemberships()->exists(),
        ];

        $present = array_keys(array_filter($blockers));

        abort_unless(
            $present === [],
            409,
            'This employee has '.implode(', ', $present).' on record and cannot be deleted. Archive them instead.',
        );

        DB::transaction(function () use ($employee, $actor) {
            $user = $employee->user;

            app(AuditLogger::class)->record(
                AuditAction::EmployeeDeleted,
                $employee,
                oldData: [
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->fullName(),
                    'email' => $user?->email,
                ],
                reason: 'Invited employee deleted before onboarding',
                actor: $actor,
            );

            // The only history an un-onboarded invite has is the "created"
            // status row written by invite() itself — it goes with them.
            $employee->statusHistory()->delete();
            $employee->delete();

            if ($user !== null) {
                DB::table('password_reset_tokens')->where('email', $user->email)->delete();
                $user->delete();
            }
        });
    }

    public function transitionStatus(
        Employee $employee,
        EmployeeStatus $to,
        string $reason,
        ?User $changedBy = null,
    ): Employee {
        $from = $employee->status;
        $employee->update(['status' => $to]);
        $this->recordStatusChange($employee, $from, $to, $reason, $changedBy);

        app(AuditLogger::class)->record(
            AuditAction::EmployeeStatusChanged, $employee,
            oldData: ['status' => $from->value], newData: ['status' => $to->value],
            reason: $reason, actor: $changedBy,
        );

        return $employee;
    }

    /**
     * Ends the employee's current team membership (if any) and starts a new
     * one — never deletes the old row, so history survives (§14).
     */
    public function transfer(Employee $employee, Team $team, ?string $effectiveDate = null): void
    {
        $effectiveDate ??= now()->toDateString();

        $employee->currentTeamMembership?->update(['ended_at' => $effectiveDate]);

        $employee->teamMemberships()->create([
            'team_id' => $team->id,
            'started_at' => $effectiveDate,
        ]);
    }

    /**
     * Ends the employee's current team membership without starting a new
     * one — they become teamless, distinct from a transfer (§14).
     */
    public function removeFromTeam(Employee $employee, ?string $effectiveDate = null): void
    {
        $employee->currentTeamMembership?->update([
            'ended_at' => $effectiveDate ?? now()->toDateString(),
        ]);
    }

    /**
     * Ends the employee's current shift assignment (if any) and starts a
     * new one — mirrors transfer(); never deletes the old row, so a shift
     * change stays auditable history (docs/PRD.md §14, §104).
     */
    public function assignShift(Employee $employee, Shift $shift, ?string $effectiveDate = null): void
    {
        $effectiveDate ??= now()->toDateString();

        $employee->currentShiftAssignment?->update(['ended_at' => $effectiveDate]);

        $employee->shiftAssignments()->create([
            'shift_id' => $shift->id,
            'started_at' => $effectiveDate,
        ]);
    }

    /**
     * Called from NewPasswordController when an invited employee sets
     * their first password — accepting the invitation IS setting the
     * password, there's no separate step (see docs/PRD.md §148 #2 and
     * the comment on that controller).
     */
    public function acceptInvitation(Employee $employee): Employee
    {
        $target = $employee->confirmation_date ? EmployeeStatus::Probation : EmployeeStatus::Active;

        return $this->transitionStatus($employee, $target, 'Invitation accepted', $employee->user);
    }

    private function recordStatusChange(
        Employee $employee,
        ?EmployeeStatus $from,
        EmployeeStatus $to,
        string $reason,
        ?User $changedBy,
    ): void {
        $employee->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $changedBy?->id,
        ]);
    }

    private function generateEmployeeCode(): string
    {
        $next = ((int) Employee::query()->max('id')) + 1;

        return 'EMP-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
