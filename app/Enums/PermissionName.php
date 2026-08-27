<?php

namespace App\Enums;

/**
 * The complete V1 permission set, docs/PRD.md §11. Adding a permission means
 * adding a case here AND a row via RolePermissionSeeder — the enum exists so
 * every check in the codebase is a typo-proof `PermissionName::X`, not a
 * bare string; the database table exists so grants stay data, not code.
 */
enum PermissionName: string
{
    case EmployeeView = 'employee.view';
    case EmployeeCreate = 'employee.create';
    case EmployeeUpdate = 'employee.update';
    case EmployeeArchive = 'employee.archive';
    case EmployeeFinancialView = 'employee.financial.view';
    case EmployeeFinancialManage = 'employee.financial.manage';

    case DepartmentView = 'department.view';
    case DepartmentManage = 'department.manage';

    case TeamView = 'team.view';
    case TeamManage = 'team.manage';

    case ShiftView = 'shift.view';
    case ShiftManage = 'shift.manage';
    case ShiftOverride = 'shift.override';

    case AttendanceView = 'attendance.view';
    case AttendanceManage = 'attendance.manage';
    case AttendanceCorrect = 'attendance.correct';

    case LeaveRequest = 'leave.request';
    case LeaveReview = 'leave.review';
    case LeaveApprove = 'leave.approve';
    case LeaveOverride = 'leave.override';
    case LeavePolicyManage = 'leave.policy.manage';
    case LeaveBalanceAdjust = 'leave.balance.adjust';

    case OvertimeView = 'overtime.view';
    case OvertimeReview = 'overtime.review';
    case OvertimeApprove = 'overtime.approve';
    case OvertimeAdjust = 'overtime.adjust';
    case OvertimePolicyManage = 'overtime.policy.manage';

    case HolidayView = 'holiday.view';
    case HolidayManage = 'holiday.manage';
    case HolidayNoticeApprove = 'holiday.notice.approve';

    case AnnouncementView = 'announcement.view';
    case AnnouncementCreate = 'announcement.create';
    case AnnouncementPublish = 'announcement.publish';

    case PayrollView = 'payroll.view';
    case PayrollPrepare = 'payroll.prepare';
    case PayrollAdjust = 'payroll.adjust';
    case PayrollFinalize = 'payroll.finalize';
    case PayrollDisputeResolve = 'payroll.dispute.resolve';

    case PayslipViewSelf = 'payslip.view_self';
    case PayslipViewAll = 'payslip.view_all';

    case ReportView = 'report.view';
    case ReportExport = 'report.export';

    case DocumentView = 'document.view';
    case DocumentManage = 'document.manage';

    case SettingsManage = 'settings.manage';
    case PayrollSettingsManage = 'payroll.settings.manage';
    case AttendanceSettingsManage = 'attendance.settings.manage';

    case AuditView = 'audit.view';

    case SystemHealthView = 'system.health.view';

    /**
     * docs/PRD.md §92.5 — mandatory 2FA for anyone holding one of these.
     */
    public function requiresTwoFactor(): bool
    {
        return match ($this) {
            self::PayrollFinalize, self::EmployeeFinancialManage, self::SettingsManage => true,
            default => false,
        };
    }
}
