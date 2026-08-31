<?php

namespace App\Enums;

/**
 * docs/PRD.md §83 — the sensitive actions that must be audited. Every one
 * is written to audit_logs inside the same DB transaction as the change it
 * records, so a rolled-back change leaves no phantom row and a successful
 * one can never lack an entry.
 */
enum AuditAction: string
{
    case AttendanceUpdated = 'ATTENDANCE_UPDATED';
    case AttendanceGraceChanged = 'ATTENDANCE_GRACE_CHANGED';
    case ShiftChanged = 'SHIFT_CHANGED';
    case LeaveApproved = 'LEAVE_APPROVED';
    case LeaveRejected = 'LEAVE_REJECTED';
    case OvertimeApproved = 'OVERTIME_APPROVED';
    case OvertimeAdjusted = 'OVERTIME_ADJUSTED';
    case SalaryChanged = 'SALARY_CHANGED';
    case PayrollAdjusted = 'PAYROLL_ADJUSTED';
    case PayrollFinalized = 'PAYROLL_FINALIZED';
    case PayrollSettingsChanged = 'PAYROLL_SETTINGS_CHANGED';
    case EmployeeStatusChanged = 'EMPLOYEE_STATUS_CHANGED';
    case EmployeeDeleted = 'EMPLOYEE_DELETED';
    case LeaveBalanceAdjusted = 'LEAVE_BALANCE_ADJUSTED';
    case PayrollDisputeRaised = 'PAYROLL_DISPUTE_RAISED';
    case PayrollDisputeResolved = 'PAYROLL_DISPUTE_RESOLVED';
    case PayrollArrearCreated = 'PAYROLL_ARREAR_CREATED';
    case PayrollArrearApplied = 'PAYROLL_ARREAR_APPLIED';
    case RoleAssigned = 'ROLE_ASSIGNED';
    case PermissionChanged = 'PERMISSION_CHANGED';
    case UserTokensRevoked = 'USER_TOKENS_REVOKED';
    case LoginFailed = 'LOGIN_FAILED';
    case ReportExported = 'REPORT_EXPORTED';
    case DocumentDownloaded = 'DOCUMENT_DOWNLOADED';
    case HolidayNoticeApproved = 'HOLIDAY_NOTICE_APPROVED';

    // docs/PRD.md §79 — operator actions from the DevOps console's Queue page.
    case QueueJobRetried = 'QUEUE_JOB_RETRIED';
    case QueueJobForgotten = 'QUEUE_JOB_FORGOTTEN';
}
