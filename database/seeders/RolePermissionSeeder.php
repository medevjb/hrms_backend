<?php

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Seeds the fixed V1 role and permission catalogue (docs/PRD.md §8, §11) and
 * each role's default capability set.
 *
 * This seeds role_permissions ONLY — which permissions a role carries in the
 * abstract. It does NOT touch user_roles: scope (SELF/TEAM/.../ALL_EMPLOYEES)
 * belongs on the individual grant, not the role, because the same role is
 * held by different people at different scopes (§10 — a Team Leader's
 * "leave.approve" applies to their own team, not every team). Assigning a
 * role to a user and picking that grant's scope is a separate, later step —
 * see HrmInstallCommand for the one seeded here (the first Admin).
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * One-line summary of each role's remit (docs/PRD.md §8). Shown on the
     * read-only roles reference screen; not used for any authorization.
     *
     * @var array<string, string>
     */
    private const ROLE_DESCRIPTIONS = [
        'Admin' => 'Full control of the system — every permission, at every scope.',
        'Head of HR' => 'Owns the HR function: people, payroll, leave and attendance policy, and org settings.',
        'HR' => 'Day-to-day HR operations — onboarding, attendance, leave and payroll preparation.',
        'Operation Manager' => 'Runs an operational chain: reviews and approves leave and overtime for the teams beneath them.',
        'Team Leader' => 'Leads a team: reviews and approves their members\' leave and overtime, sees team attendance.',
        'Team Member' => 'An individual employee — own attendance, leave requests, payslips and announcements.',
        'System Admin / DevOps' => 'Technical operator: system health and audit visibility only, no employee data.',
    ];

    /** @var array<string, list<PermissionName>> */
    private const ROLE_PERMISSIONS = [
        'Head of HR' => [
            PermissionName::EmployeeView,
            PermissionName::EmployeeCreate,
            PermissionName::EmployeeUpdate,
            PermissionName::EmployeeArchive,
            PermissionName::EmployeeFinancialView,
            PermissionName::EmployeeFinancialManage,
            PermissionName::DepartmentView,
            PermissionName::DepartmentManage,
            PermissionName::TeamView,
            PermissionName::TeamManage,
            PermissionName::ShiftView,
            PermissionName::ShiftManage,
            PermissionName::ShiftOverride,
            PermissionName::AttendanceView,
            PermissionName::AttendanceManage,
            PermissionName::AttendanceCorrect,
            PermissionName::LeaveRequest,
            PermissionName::LeaveReview,
            PermissionName::LeaveApprove,
            PermissionName::LeaveOverride,
            PermissionName::LeavePolicyManage,
            PermissionName::LeaveBalanceAdjust,
            PermissionName::OvertimeView,
            PermissionName::OvertimeReview,
            PermissionName::OvertimeApprove,
            PermissionName::OvertimeAdjust,
            PermissionName::OvertimePolicyManage,
            PermissionName::HolidayView,
            PermissionName::HolidayManage,
            PermissionName::HolidayNoticeApprove,
            PermissionName::AnnouncementView,
            PermissionName::AnnouncementCreate,
            PermissionName::AnnouncementPublish,
            PermissionName::PayrollView,
            PermissionName::PayrollPrepare,
            PermissionName::PayrollAdjust,
            PermissionName::PayrollFinalize,
            PermissionName::PayrollDisputeResolve,
            PermissionName::PayslipViewSelf,
            PermissionName::PayslipViewAll,
            PermissionName::ReportView,
            PermissionName::ReportExport,
            PermissionName::DocumentView,
            PermissionName::DocumentManage,
            PermissionName::SettingsManage,
            PermissionName::PayrollSettingsManage,
            PermissionName::AttendanceSettingsManage,
            PermissionName::AuditView,
        ],

        'HR' => [
            PermissionName::EmployeeView,
            PermissionName::EmployeeCreate,
            PermissionName::EmployeeUpdate,
            PermissionName::EmployeeFinancialView,
            PermissionName::DepartmentView,
            PermissionName::TeamView,
            PermissionName::ShiftView,
            PermissionName::ShiftManage,
            PermissionName::AttendanceView,
            PermissionName::AttendanceManage,
            PermissionName::AttendanceCorrect,
            PermissionName::LeaveRequest,
            PermissionName::LeaveReview,
            PermissionName::LeaveApprove,
            PermissionName::LeaveBalanceAdjust,
            PermissionName::OvertimeView,
            PermissionName::OvertimeReview,
            PermissionName::OvertimeApprove,
            PermissionName::HolidayView,
            PermissionName::HolidayManage,
            PermissionName::AnnouncementView,
            PermissionName::AnnouncementCreate,
            PermissionName::PayrollView,
            PermissionName::PayrollPrepare,
            PermissionName::PayrollAdjust,
            PermissionName::PayslipViewSelf,
            PermissionName::PayslipViewAll,
            PermissionName::ReportView,
            PermissionName::DocumentView,
            PermissionName::DocumentManage,
            PermissionName::AuditView,
        ],

        'Operation Manager' => [
            PermissionName::EmployeeView,
            PermissionName::TeamView,
            PermissionName::ShiftView,
            PermissionName::AttendanceView,
            PermissionName::LeaveRequest,
            PermissionName::LeaveReview,
            PermissionName::LeaveApprove,
            PermissionName::OvertimeView,
            PermissionName::OvertimeReview,
            PermissionName::OvertimeApprove,
            PermissionName::HolidayView,
            PermissionName::AnnouncementView,
            PermissionName::PayslipViewSelf,
            PermissionName::ReportView,
        ],

        'Team Leader' => [
            PermissionName::EmployeeView,
            PermissionName::TeamView,
            PermissionName::ShiftView,
            PermissionName::AttendanceView,
            PermissionName::LeaveRequest,
            PermissionName::LeaveReview,
            PermissionName::LeaveApprove,
            PermissionName::OvertimeView,
            PermissionName::OvertimeReview,
            PermissionName::OvertimeApprove,
            PermissionName::HolidayView,
            PermissionName::AnnouncementView,
            PermissionName::PayslipViewSelf,
        ],

        'Team Member' => [
            PermissionName::AttendanceView,
            PermissionName::LeaveRequest,
            PermissionName::HolidayView,
            PermissionName::AnnouncementView,
            PermissionName::PayslipViewSelf,
        ],

        'System Admin / DevOps' => [
            PermissionName::SystemHealthView,
            PermissionName::AuditView,
        ],
    ];

    public function run(): void
    {
        $permissionsByName = collect(PermissionName::cases())
            ->mapWithKeys(fn (PermissionName $permission) => [
                $permission->value => Permission::query()->firstOrCreate(
                    ['name' => $permission->value],
                ),
            ]);

        // Admin holds every permission — spelling that out case-by-case above
        // would just be the full enum list with extra steps.
        $adminRole = Role::query()->updateOrCreate(
            ['name' => 'Admin'],
            ['description' => self::ROLE_DESCRIPTIONS['Admin']],
        );
        $adminRole->permissions()->sync($permissionsByName->pluck('id'));

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                ['description' => self::ROLE_DESCRIPTIONS[$roleName]],
            );

            $permissionIds = collect($permissions)
                ->map(fn (PermissionName $permission) => $permissionsByName[$permission->value]->id);

            $role->permissions()->sync($permissionIds);
        }
    }
}
