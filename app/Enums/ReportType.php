<?php

namespace App\Enums;

/**
 * docs/PRD.md §99 — the V1 report set. Each maps to a builder method on
 * ReportService; all support the same date / department / team / employee
 * filters and a CSV export (§11 report.export).
 */
enum ReportType: string
{
    case EmployeeDirectory = 'employee_directory';
    case Attendance = 'attendance';
    case LateAttendance = 'late_attendance';
    case Absence = 'absence';
    case Leave = 'leave';
    case LeaveBalance = 'leave_balance';
    case Overtime = 'overtime';
    case Payroll = 'payroll';
    case PayrollDeductions = 'payroll_deductions';

    public function title(): string
    {
        return match ($this) {
            self::EmployeeDirectory => 'Employee directory',
            self::Attendance => 'Attendance',
            self::LateAttendance => 'Late attendance',
            self::Absence => 'Absence',
            self::Leave => 'Leave',
            self::LeaveBalance => 'Leave balance',
            self::Overtime => 'Overtime',
            self::Payroll => 'Payroll',
            self::PayrollDeductions => 'Payroll deductions',
        };
    }

    /**
     * §99 — the reports that read a payroll period rather than a date range.
     */
    public function usesPayrollPeriod(): bool
    {
        return match ($this) {
            self::Payroll, self::PayrollDeductions => true,
            default => false,
        };
    }
}
