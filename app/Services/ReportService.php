<?php

namespace App\Services;

use App\Enums\PayrollLineCategory;
use App\Enums\PermissionName;
use App\Enums\ReportType;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\OrganizationSettings;
use App\Models\OvertimeRecord;
use App\Models\PayrollEntry;
use App\Models\PayrollEntryLine;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\Report;
use Illuminate\Database\Eloquent\Builder;

/**
 * docs/PRD.md §99 — the V1 report builders. Every report is narrowed to the
 * employees the caller may see through `report.view` (§10) and to the
 * date / department / team / employee filters, then materialised as a
 * Report value object for JSON preview or CSV export.
 */
class ReportService
{
    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    /**
     * @param  array{period?: string|null, date_from?: string|null, date_to?: string|null, department_id?: int|null, team_id?: int|null, employee_id?: int|null, payroll_period_id?: int|null}  $filters
     */
    public function build(ReportType $type, array $filters, User $user): Report
    {
        $employeeIds = $this->allowedEmployeeIds($user, $filters);

        return match ($type) {
            ReportType::EmployeeDirectory => $this->employeeDirectory($employeeIds),
            ReportType::Attendance => $this->attendance($employeeIds, $filters, null),
            ReportType::LateAttendance => $this->attendance($employeeIds, $filters, 'LATE'),
            ReportType::Absence => $this->attendance($employeeIds, $filters, 'ABSENT'),
            ReportType::Leave => $this->leave($employeeIds, $filters),
            ReportType::LeaveBalance => $this->leaveBalance($employeeIds),
            ReportType::Overtime => $this->overtime($employeeIds, $filters),
            ReportType::Payroll => $this->payroll($employeeIds, $filters),
            ReportType::PayrollDeductions => $this->payrollDeductions($employeeIds, $filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<int>|null null means unrestricted (every employee).
     */
    private function allowedEmployeeIds(User $user, array $filters): ?array
    {
        $scoped = $this->scopeResolver->employeeIdsFor($user, PermissionName::ReportView);

        $narrowed = null;
        if (! empty($filters['department_id'])) {
            $teamIds = Team::query()->where('department_id', $filters['department_id'])->pluck('id')->all();
            $narrowed = $this->teamMemberIds($teamIds);
        } elseif (! empty($filters['team_id'])) {
            $narrowed = $this->teamMemberIds([(int) $filters['team_id']]);
        } elseif (! empty($filters['employee_id'])) {
            $narrowed = [(int) $filters['employee_id']];
        }

        if ($scoped === null) {
            return $narrowed;
        }

        return $narrowed === null
            ? $scoped
            : array_values(array_intersect($scoped, $narrowed));
    }

    /**
     * @param  array<int, mixed>  $teamIds
     * @return list<int>
     */
    private function teamMemberIds(array $teamIds): array
    {
        $ids = TeamMember::query()
            ->whereIn('team_id', $teamIds)
            ->whereNull('ended_at')
            ->pluck('employee_id')
            ->all();

        return array_values(array_map('intval', $ids));
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  list<int>|null  $employeeIds
     */
    private function restrict(Builder $query, ?array $employeeIds, string $column = 'employee_id'): void
    {
        if ($employeeIds !== null) {
            $query->whereIn($column, $employeeIds);
        }
    }

    /**
     * The window a date report covers. With no explicit range it is the
     * organization reporting month (docs/PRD.md §85) — either the one named
     * by a `period` key (`YYYY-MM`) or the current one. An explicit
     * `date_from` / `date_to` overrides either edge.
     *
     * @param  array<string, mixed>  $filters
     * @return array{string, string}
     */
    private function dateRange(array $filters): array
    {
        $service = app(ReportingPeriodService::class);

        $period = ! empty($filters['period'])
            ? $service->forKey((string) $filters['period'], OrganizationSettings::current()->reporting_month_cutoff_day)
            : $service->current();

        return [
            (string) ($filters['date_from'] ?? $period->startDate->toDateString()),
            (string) ($filters['date_to'] ?? $period->endDate->toDateString()),
        ];
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, string|int|float|null>>
     */
    private function rows(array $rows): array
    {
        /** @var array<int, array<string, string|int|float|null>> $normalized */
        $normalized = array_values($rows);

        return $normalized;
    }

    /**
     * @param  list<int>|null  $employeeIds
     */
    private function employeeDirectory(?array $employeeIds): Report
    {
        $query = Employee::query()->with('currentTeamMembership.team.department')->orderBy('employee_code');
        $this->restrict($query, $employeeIds, 'id');

        $rows = $query->get()->map(function (Employee $employee) {
            $team = $employee->currentTeam();

            return [
                'employee_code' => $employee->employee_code,
                'name' => $employee->fullName(),
                'designation' => $employee->designation,
                'department' => $team !== null ? ($team->department->name ?? '') : '',
                'team' => $team !== null ? $team->name : '',
                'status' => $employee->status->value,
                'employment_type' => $employee->employment_type->value,
                'joining_date' => $employee->joining_date->toDateString(),
            ];
        })->all();

        return new Report(ReportType::EmployeeDirectory, [
            ['key' => 'employee_code', 'label' => 'Employee code'],
            ['key' => 'name', 'label' => 'Name'],
            ['key' => 'designation', 'label' => 'Designation'],
            ['key' => 'department', 'label' => 'Department'],
            ['key' => 'team', 'label' => 'Team'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'employment_type', 'label' => 'Employment type'],
            ['key' => 'joining_date', 'label' => 'Joining date'],
        ], $this->rows($rows));
    }

    /**
     * @param  list<int>|null  $employeeIds
     * @param  array<string, mixed>  $filters
     */
    private function attendance(?array $employeeIds, array $filters, ?string $onlyStatus): Report
    {
        [$from, $to] = $this->dateRange($filters);

        $query = AttendanceRecord::query()
            ->with(['employee', 'shift'])
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->orderBy('employee_id');
        $this->restrict($query, $employeeIds);

        if ($onlyStatus !== null) {
            $query->where('status', $onlyStatus);
        }

        $rows = $query->get()->map(fn (AttendanceRecord $record) => [
            'employee' => $record->employee->fullName(),
            'employee_code' => $record->employee->employee_code,
            'date' => $record->work_date->toDateString(),
            'shift' => $record->shift ? $record->shift->name : '',
            'shift_start' => $record->shift_start_used?->toIso8601String() ?? '',
            'grace_minutes' => $record->grace_minutes_used ?? 0,
            'grace_end' => $record->shift_start_used && $record->grace_minutes_used !== null
                ? $record->shift_start_used->copy()->addMinutes($record->grace_minutes_used)->toIso8601String()
                : '',
            'check_in' => $record->check_in?->toIso8601String() ?? '',
            'status' => $record->status->value,
            'late_minutes' => $record->late_minutes ?? 0,
        ])->all();

        $type = match ($onlyStatus) {
            'LATE' => ReportType::LateAttendance,
            'ABSENT' => ReportType::Absence,
            default => ReportType::Attendance,
        };

        return new Report($type, [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'employee_code', 'label' => 'Code'],
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'shift', 'label' => 'Shift'],
            ['key' => 'shift_start', 'label' => 'Shift start'],
            ['key' => 'grace_minutes', 'label' => 'Grace minutes'],
            ['key' => 'grace_end', 'label' => 'Grace end'],
            ['key' => 'check_in', 'label' => 'Actual check-in'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'late_minutes', 'label' => 'Late minutes'],
        ], $this->rows($rows));
    }

    /**
     * @param  list<int>|null  $employeeIds
     * @param  array<string, mixed>  $filters
     */
    private function leave(?array $employeeIds, array $filters): Report
    {
        [$from, $to] = $this->dateRange($filters);

        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType'])
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->orderBy('start_date');
        $this->restrict($query, $employeeIds);

        $rows = $query->get()->map(fn (LeaveRequest $request) => [
            'employee' => $request->employee->fullName(),
            'leave_type' => $request->leaveType->name,
            'start_date' => $request->start_date->toDateString(),
            'end_date' => $request->end_date->toDateString(),
            'days' => (float) $request->days_requested,
            'status' => $request->status->value,
            'decided_at' => $request->decided_at?->toIso8601String() ?? '',
        ])->all();

        return new Report(ReportType::Leave, [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'leave_type', 'label' => 'Leave type'],
            ['key' => 'start_date', 'label' => 'Start'],
            ['key' => 'end_date', 'label' => 'End'],
            ['key' => 'days', 'label' => 'Days'],
            ['key' => 'status', 'label' => 'Status'],
            ['key' => 'decided_at', 'label' => 'Decided at'],
        ], $this->rows($rows));
    }

    /**
     * @param  list<int>|null  $employeeIds
     */
    private function leaveBalance(?array $employeeIds): Report
    {
        $query = LeaveBalance::query()->with(['employee', 'leaveType'])->orderBy('employee_id');
        $this->restrict($query, $employeeIds);

        $rows = $query->get()->map(fn (LeaveBalance $balance) => [
            'employee' => $balance->employee->fullName(),
            'leave_type' => $balance->leaveType->name,
            'leave_year' => $balance->leave_year,
            'balance' => (float) $balance->balance,
        ])->all();

        return new Report(ReportType::LeaveBalance, [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'leave_type', 'label' => 'Leave type'],
            ['key' => 'leave_year', 'label' => 'Leave year'],
            ['key' => 'balance', 'label' => 'Balance'],
        ], $this->rows($rows));
    }

    /**
     * @param  list<int>|null  $employeeIds
     * @param  array<string, mixed>  $filters
     */
    private function overtime(?array $employeeIds, array $filters): Report
    {
        [$from, $to] = $this->dateRange($filters);

        $query = OvertimeRecord::query()
            ->with('employee')
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date');
        $this->restrict($query, $employeeIds);

        $rows = $query->get()->map(fn (OvertimeRecord $record) => [
            'employee' => $record->employee->fullName(),
            'work_date' => $record->work_date->toDateString(),
            'type' => $record->type->value,
            'worked_minutes' => $record->worked_minutes,
            'days' => $record->effectiveOvertimeDays(),
            'status' => $record->status->value,
        ])->all();

        return new Report(ReportType::Overtime, [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'work_date', 'label' => 'Work date'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'worked_minutes', 'label' => 'Worked minutes'],
            ['key' => 'days', 'label' => 'Overtime days'],
            ['key' => 'status', 'label' => 'Status'],
        ], $this->rows($rows));
    }

    /**
     * @param  list<int>|null  $employeeIds
     * @param  array<string, mixed>  $filters
     */
    private function payroll(?array $employeeIds, array $filters): Report
    {
        $query = PayrollEntry::query()->with(['employee', 'period'])->orderBy('payroll_period_id');
        $this->restrict($query, $employeeIds);

        if (! empty($filters['payroll_period_id'])) {
            $query->where('payroll_period_id', $filters['payroll_period_id']);
        }

        $rows = $query->get()->map(fn (PayrollEntry $entry) => [
            'employee' => $entry->employee->fullName(),
            'period' => $entry->period->label,
            'gross_earnings' => (string) $entry->gross_earnings,
            'total_deductions' => (string) $entry->total_deductions,
            'net_salary' => (string) $entry->net_salary,
            'status' => $entry->status->value,
        ])->all();

        return new Report(ReportType::Payroll, [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'gross_earnings', 'label' => 'Gross'],
            ['key' => 'total_deductions', 'label' => 'Deductions'],
            ['key' => 'net_salary', 'label' => 'Net'],
            ['key' => 'status', 'label' => 'Status'],
        ], $this->rows($rows));
    }

    /**
     * @param  list<int>|null  $employeeIds
     * @param  array<string, mixed>  $filters
     */
    private function payrollDeductions(?array $employeeIds, array $filters): Report
    {
        $query = PayrollEntryLine::query()
            ->with(['entry.employee', 'entry.period'])
            ->where('category', PayrollLineCategory::Deduction)
            ->whereHas('entry', function (Builder $entry) use ($employeeIds, $filters) {
                $this->restrict($entry, $employeeIds);
                if (! empty($filters['payroll_period_id'])) {
                    $entry->where('payroll_period_id', $filters['payroll_period_id']);
                }
            });

        $rows = $query->get()->map(fn (PayrollEntryLine $line) => [
            'employee' => $line->entry->employee->fullName(),
            'period' => $line->entry->period->label,
            'type' => $line->type->value,
            'label' => $line->label,
            'amount' => (string) $line->amount,
        ])->all();

        return new Report(ReportType::PayrollDeductions, [
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'period', 'label' => 'Period'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'label', 'label' => 'Label'],
            ['key' => 'amount', 'label' => 'Amount'],
        ], $this->rows($rows));
    }
}
