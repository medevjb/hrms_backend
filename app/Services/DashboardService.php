<?php

namespace App\Services;

use App\Enums\AnnouncementStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveStatus;
use App\Enums\OvertimeStatus;
use App\Enums\PayrollAcknowledgementStatus;
use App\Enums\PayrollDisputeStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PermissionName;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HolidayNotice;
use App\Models\LeaveRequest;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollDispute;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §73–§78 — one role-aware payload. Every widget is present
 * only when the caller's permissions warrant it and is computed from real
 * data scoped through ScopeResolver — no fabricated numbers, no widget the
 * caller can't act on.
 */
class DashboardService
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $settings = OrganizationSettings::current();
        $today = Carbon::now($settings->timezone)->toDateString();

        $widgets = [];

        if ($user->employee !== null) {
            $widgets['me'] = $this->me($user->employee, $today);
        }

        if ($this->can($user, PermissionName::AttendanceView)) {
            $widgets['attendance_today'] = $this->attendanceToday($user, $today);
        }

        $approvals = $this->pendingApprovals($user);
        if ($approvals !== []) {
            $widgets['pending_approvals'] = $approvals;
        }

        if ($this->can($user, PermissionName::EmployeeView)) {
            $widgets['workforce'] = $this->workforce();
        }

        if ($this->can($user, PermissionName::PayrollView)) {
            $widgets['payroll'] = $this->payroll();
        }

        if ($this->can($user, PermissionName::HolidayView)) {
            $widgets['upcoming_holidays'] = $this->upcomingHolidays($today);
        }

        if ($this->can($user, PermissionName::AnnouncementView) && $user->employee !== null) {
            $widgets['announcements'] = $this->announcements($user->employee);
        }

        return [
            'as_of' => Carbon::now()->toIso8601String(),
            'roles' => $user->roles()->pluck('name'),
            'widgets' => $widgets,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function me(Employee $employee, string $today): array
    {
        $todayRecord = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $today)
            ->first();

        $leaveBalances = $employee->leaveBalances()
            ->with('leaveType')
            ->get()
            ->map(fn ($balance) => [
                'leave_type' => $balance->leaveType->name,
                'balance' => (float) $balance->balance,
            ]);

        return [
            'today' => $todayRecord ? [
                'status' => $todayRecord->status->value,
                'check_in' => $todayRecord->check_in?->toIso8601String(),
                'check_out' => $todayRecord->check_out?->toIso8601String(),
                'worked_minutes' => $todayRecord->worked_minutes,
            ] : null,
            'leave_balances' => $leaveBalances,
            'pending_leave' => LeaveRequest::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', [LeaveStatus::Submitted, LeaveStatus::TeamLeaderApproved, LeaveStatus::OperationManagerApproved])
                ->count(),
            'overtime_pending' => OvertimeRecord::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', [OvertimeStatus::PendingTeamLeader, OvertimeStatus::PendingOperationManager, OvertimeStatus::PendingHr])
                ->count(),
            'payslip_awaiting_confirmation' => PayrollEntry::query()
                ->where('employee_id', $employee->id)
                ->where('acknowledgement_status', PayrollAcknowledgementStatus::Pending)
                ->whereNotNull('released_at')
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function attendanceToday(User $user, string $today): array
    {
        $ids = $this->scopeResolver->employeeIdsFor($user, PermissionName::AttendanceView);

        $query = AttendanceRecord::query()->whereDate('work_date', $today);
        if ($ids !== null) {
            $query->whereIn('employee_id', $ids);
        }

        $byStatus = $query->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'present' => (int) ($byStatus[AttendanceStatus::Present->value] ?? 0),
            'late' => (int) ($byStatus[AttendanceStatus::Late->value] ?? 0),
            'absent' => (int) ($byStatus[AttendanceStatus::Absent->value] ?? 0),
            'on_leave' => (int) ($byStatus[AttendanceStatus::OnLeave->value] ?? 0),
            'missing_checkout' => (int) ($byStatus[AttendanceStatus::MissingCheckout->value] ?? 0),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function pendingApprovals(User $user): array
    {
        $approvals = [];

        if ($this->can($user, PermissionName::LeaveApprove)) {
            $approvals['leave'] = $this->pendingLeaveApprovals($user);
        }

        if ($this->can($user, PermissionName::OvertimeApprove)) {
            $approvals['overtime'] = $this->pendingOvertimeApprovals($user);
        }

        if ($this->can($user, PermissionName::HolidayNoticeApprove)) {
            $approvals['holiday_notices'] = HolidayNotice::query()->where('status', 'PENDING_APPROVAL')->count();
        }

        if ($this->can($user, PermissionName::PayrollDisputeResolve)) {
            $approvals['payroll_disputes'] = PayrollDispute::query()->where('status', PayrollDisputeStatus::Open)->count();
        }

        return $approvals;
    }

    private function pendingLeaveApprovals(User $user): int
    {
        return LeaveRequest::query()->whereNotNull('current_stage')->where(function ($outer) use ($user) {
            if ($user->hasRole('Team Leader')) {
                $outer->orWhere(fn ($q) => $q->where('current_stage', 'TEAM_LEADER')
                    ->whereHas('employee.currentTeamMembership.team', fn ($t) => $t->where('team_leader_id', $user->employee?->id)));
            }
            if ($user->hasRole('Operation Manager')) {
                $outer->orWhere(fn ($q) => $q->where('current_stage', 'OPERATION_MANAGER')
                    ->whereHas('employee.currentTeamMembership.team.department', fn ($d) => $d->where('operation_manager_id', $user->employee?->id)));
            }
            if ($user->hasRole('HR') || $user->hasRole('Head of HR') || $user->hasRole('Admin')) {
                $outer->orWhere('current_stage', 'HR');
            }
            if ($user->hasRole('Head of HR') || $user->hasRole('Admin')) {
                $outer->orWhere('current_stage', 'HEAD_HR');
            }
            if ($user->hasRole('Admin')) {
                $outer->orWhere('current_stage', 'ADMIN');
            }
        })->count();
    }

    private function pendingOvertimeApprovals(User $user): int
    {
        if ($user->hasRole('Head of HR') || $user->hasRole('Admin')) {
            return OvertimeRecord::query()->whereNotNull('current_stage')->count();
        }

        return OvertimeRecord::query()->whereNotNull('current_stage')->where(function ($outer) use ($user) {
            if ($user->hasRole('Team Leader')) {
                $outer->orWhere(fn ($q) => $q->where('current_stage', 'TEAM_LEADER')
                    ->whereHas('employee.currentTeamMembership.team', fn ($t) => $t->where('team_leader_id', $user->employee?->id)));
            }
            if ($user->hasRole('Operation Manager')) {
                $outer->orWhere(fn ($q) => $q->where('current_stage', 'OPERATION_MANAGER')
                    ->whereHas('employee.currentTeamMembership.team.department', fn ($d) => $d->where('operation_manager_id', $user->employee?->id)));
            }
            if ($user->hasRole('HR')) {
                $outer->orWhere('current_stage', 'HR');
            }
        })->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function workforce(): array
    {
        $byStatus = Employee::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'total' => (int) $byStatus->sum(),
            'active' => (int) ($byStatus[EmployeeStatus::Active->value] ?? 0),
            'by_status' => $byStatus->mapWithKeys(fn ($total, $status) => [$status => (int) $total]),
            'departments' => Department::query()->count(),
            'teams' => Team::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payroll(): array
    {
        $current = PayrollPeriod::query()->orderByDesc('start_date')->first();

        return [
            'current_period' => $current ? [
                'id' => $current->id,
                'label' => $current->label,
                'status' => $current->status->value,
                'entries' => $current->entries()->count(),
                'awaiting_confirmation' => $current->entries()
                    ->where('acknowledgement_status', PayrollAcknowledgementStatus::Pending)
                    ->whereNotNull('released_at')
                    ->count(),
            ] : null,
            'open_periods' => PayrollPeriod::query()
                ->whereNotIn('status', [PayrollPeriodStatus::Finalized, PayrollPeriodStatus::Paid, PayrollPeriodStatus::Locked])
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function upcomingHolidays(string $today): array
    {
        return Holiday::query()
            ->where('active', true)
            ->whereDate('date', '>=', $today)
            ->orderBy('date')
            ->limit(5)
            ->get()
            ->map(fn (Holiday $holiday) => [
                'title' => $holiday->title,
                'date' => $holiday->date->toDateString(),
                'type' => $holiday->type->value,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function announcements(Employee $employee): array
    {
        $service = app(AnnouncementService::class);

        $visible = $service->visibleTo($employee)
            ->with('reads')
            ->latest('published_at')
            ->limit(5)
            ->get();

        return [
            'recent' => $visible->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'type' => $announcement->type->value,
                'published_at' => $announcement->published_at?->toIso8601String(),
            ]),
            'unread' => $service->visibleTo($employee)
                ->where('status', AnnouncementStatus::Published)
                ->whereDoesntHave('reads', fn ($q) => $q->where('employee_id', $employee->id))
                ->count(),
        ];
    }

    private function can(User $user, PermissionName $permission): bool
    {
        return $user->hasPermission($permission);
    }
}
