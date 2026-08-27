<?php

namespace App\Services;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
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
            'overtime_eligible' => $data['overtime_eligible'] ?? true,
        ]);

        $this->recordStatusChange($employee, null, EmployeeStatus::Invited, 'Employee created', $invitedBy);

        Password::broker('employee_invitations')->sendResetLink($user->only('email'));

        return $employee;
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
