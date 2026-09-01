<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorAuthenticatedSessionController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeSalaryController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\HolidayNoticeController;
use App\Http\Controllers\Api\V1\LatePenaltyRuleController;
use App\Http\Controllers\Api\V1\LeaveBalanceController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\OvertimeController;
use App\Http\Controllers\Api\V1\PayrollDisputeController;
use App\Http\Controllers\Api\V1\PayrollEntryController;
use App\Http\Controllers\Api\V1\PayrollPeriodController;
use App\Http\Controllers\Api\V1\PersonalEventController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SalaryComponentController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\ShiftOverrideController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login');
    Route::post('two-factor-challenge', [TwoFactorAuthenticatedSessionController::class, 'store'])
        ->name('two-factor-challenge');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('forgot-password');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
        Route::get('me', [AuthenticatedSessionController::class, 'show'])->name('me');

        // Self-service, every authenticated user — no permission (§92.1).
        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('profile/photo', [ProfileController::class, 'showPhoto'])->name('profile.photo.show');
        Route::post('profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
        Route::delete('profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.destroy');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    });
});

// Public — the sign-in screen and the browser tab render branded before
// anyone has a session (§85).
Route::prefix('branding')->name('branding.')->group(function () {
    Route::get('/', [BrandingController::class, 'show'])->name('show');
    Route::get('logo', [BrandingController::class, 'logo'])->name('logo');
    Route::get('favicon', [BrandingController::class, 'favicon'])->name('favicon');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'show'])->name('dashboard.show');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('roles/{role}', [RoleController::class, 'show'])->name('roles.show');

    Route::get('users/{user}/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
    Route::post('users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
    Route::delete('users/{user}/roles/{userRole}', [UserRoleController::class, 'destroy'])
        ->name('users.roles.destroy');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::patch('employees/weekly-offs', [EmployeeController::class, 'assignWeeklyOff'])
        ->name('employees.weekly-offs.update');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('employees/{employee}/resend-invitation', [EmployeeController::class, 'resendInvitation'])
        ->name('employees.resend-invitation');
    Route::patch('employees/{employee}/status', [EmployeeController::class, 'updateStatus'])
        ->name('employees.update-status');
    Route::post('employees/{employee}/transfer', [EmployeeController::class, 'transfer'])
        ->name('employees.transfer');
    Route::post('employees/{employee}/assign-shift', [EmployeeController::class, 'assignShift'])
        ->name('employees.assign-shift');
    Route::get('employees/{employee}/salary', [EmployeeSalaryController::class, 'show'])->name('employees.salary.show');
    Route::put('employees/{employee}/salary', [EmployeeSalaryController::class, 'update'])->name('employees.salary.update');
    Route::get('employees/{employee}/documents', [DocumentController::class, 'index'])->name('employees.documents.index');
    Route::post('employees/{employee}/documents', [DocumentController::class, 'store'])->name('employees.documents.store');

    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('system/health', [SystemHealthController::class, 'show'])->name('system.health');

    Route::get('reports', [ReportController::class, 'types'])->name('reports.types');
    Route::get('reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::delete('teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::get('teams/{team}/members', [TeamController::class, 'members'])->name('teams.members.index');
    Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
    Route::delete('teams/{team}/members/{employee}', [TeamController::class, 'removeMember'])
        ->name('teams.members.destroy');

    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::delete('shifts/{shift}', [ShiftController::class, 'destroy'])->name('shifts.destroy');
    Route::post('shift-overrides', [ShiftOverrideController::class, 'store'])->name('shift-overrides.store');

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    // Personal calendar notes — every employee, their own only (§54.1).
    Route::get('personal-events', [PersonalEventController::class, 'index'])->name('personal-events.index');
    Route::post('personal-events', [PersonalEventController::class, 'store'])->name('personal-events.store');
    Route::put('personal-events/{personalEvent}', [PersonalEventController::class, 'update'])
        ->name('personal-events.update');
    Route::delete('personal-events/{personalEvent}', [PersonalEventController::class, 'destroy'])
        ->name('personal-events.destroy');

    Route::get('holiday-notices', [HolidayNoticeController::class, 'index'])->name('holiday-notices.index');
    Route::post('holiday-notices/{holidayNotice}/approve', [HolidayNoticeController::class, 'approve'])
        ->name('holiday-notices.approve');
    Route::post('holiday-notices/{holidayNotice}/dismiss', [HolidayNoticeController::class, 'dismiss'])
        ->name('holiday-notices.dismiss');
    Route::get('holiday-notices/{holidayNotice}/download', [HolidayNoticeController::class, 'download'])
        ->name('holiday-notices.download');

    Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    Route::post('announcements/{announcement}/publish', [AnnouncementController::class, 'publish'])
        ->name('announcements.publish');
    Route::post('announcements/{announcement}/read', [AnnouncementController::class, 'read'])
        ->name('announcements.read');

    Route::get('attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
    Route::post('attendance/check-in', [AttendanceController::class, 'checkIn'])->name('attendance.check-in');
    Route::post('attendance/check-out', [AttendanceController::class, 'checkOut'])->name('attendance.check-out');
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::patch('attendance/{attendanceRecord}/adjust', [AttendanceController::class, 'adjust'])
        ->name('attendance.adjust');

    Route::get('leave-types', [LeaveTypeController::class, 'index'])->name('leave-types.index');
    Route::post('leave-types', [LeaveTypeController::class, 'store'])->name('leave-types.store');
    Route::put('leave-types/{leaveType}', [LeaveTypeController::class, 'update'])->name('leave-types.update');
    Route::delete('leave-types/{leaveType}', [LeaveTypeController::class, 'destroy'])->name('leave-types.destroy');

    Route::get('leave-balances', [LeaveBalanceController::class, 'index'])->name('leave-balances.index');
    Route::post('leave-balances/bulk-adjust', [LeaveBalanceController::class, 'bulkAdjust'])
        ->name('leave-balances.bulk-adjust');
    Route::patch('leave-balances/{leaveBalance}/adjust', [LeaveBalanceController::class, 'adjust'])
        ->name('leave-balances.adjust');

    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::get('leave-requests/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave-requests.show');
    Route::post('leave-requests/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])
        ->name('leave-requests.approve');
    Route::post('leave-requests/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])
        ->name('leave-requests.reject');
    Route::post('leave-requests/{leaveRequest}/direct-approve', [LeaveRequestController::class, 'directApprove'])
        ->name('leave-requests.direct-approve');
    Route::post('leave-requests/{leaveRequest}/cancel', [LeaveRequestController::class, 'cancel'])
        ->name('leave-requests.cancel');

    Route::get('overtime', [OvertimeController::class, 'index'])->name('overtime.index');
    Route::get('overtime/{overtimeRecord}', [OvertimeController::class, 'show'])->name('overtime.show');
    Route::post('overtime/{overtimeRecord}/approve', [OvertimeController::class, 'approve'])
        ->name('overtime.approve');
    Route::post('overtime/{overtimeRecord}/reject', [OvertimeController::class, 'reject'])
        ->name('overtime.reject');
    Route::patch('overtime/{overtimeRecord}/adjust', [OvertimeController::class, 'adjust'])
        ->name('overtime.adjust');

    Route::get('salary-components', [SalaryComponentController::class, 'index'])->name('salary-components.index');
    Route::post('salary-components', [SalaryComponentController::class, 'store'])->name('salary-components.store');
    Route::put('salary-components/{salaryComponent}', [SalaryComponentController::class, 'update'])->name('salary-components.update');
    Route::delete('salary-components/{salaryComponent}', [SalaryComponentController::class, 'destroy'])->name('salary-components.destroy');

    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('periods', [PayrollPeriodController::class, 'index'])->name('periods.index');
        Route::post('periods', [PayrollPeriodController::class, 'store'])->name('periods.store');
        Route::get('periods/{payrollPeriod}', [PayrollPeriodController::class, 'show'])->name('periods.show');
        Route::post('periods/{payrollPeriod}/generate', [PayrollPeriodController::class, 'generate'])
            ->name('periods.generate');
        Route::post('periods/{payrollPeriod}/review', [PayrollPeriodController::class, 'review'])->name('periods.review');
        Route::post('periods/{payrollPeriod}/release', [PayrollPeriodController::class, 'release'])->name('periods.release');
        Route::post('periods/{payrollPeriod}/finalize', [PayrollPeriodController::class, 'finalize'])->name('periods.finalize');
        Route::post('periods/{payrollPeriod}/mark-paid', [PayrollPeriodController::class, 'markPaid'])->name('periods.mark-paid');
        Route::post('periods/{payrollPeriod}/lock', [PayrollPeriodController::class, 'lock'])->name('periods.lock');
        Route::get('periods/{payrollPeriod}/runs', [PayrollPeriodController::class, 'runs'])->name('periods.runs');
        Route::get('periods/{payrollPeriod}/arrears', [PayrollPeriodController::class, 'arrears'])->name('periods.arrears');
        Route::post('periods/{payrollPeriod}/arrears', [PayrollPeriodController::class, 'storeArrear'])->name('periods.arrears.store');

        Route::get('entries', [PayrollEntryController::class, 'index'])->name('entries.index');
        Route::get('entries/{payrollEntry}', [PayrollEntryController::class, 'show'])->name('entries.show');
        Route::post('entries/{payrollEntry}/adjust', [PayrollEntryController::class, 'adjust'])->name('entries.adjust');
        Route::post('entries/{payrollEntry}/acknowledge', [PayrollEntryController::class, 'acknowledge'])->name('entries.acknowledge');
        Route::post('entries/{payrollEntry}/dispute', [PayrollEntryController::class, 'dispute'])->name('entries.dispute');
        Route::get('entries/{payrollEntry}/payslip', [PayrollEntryController::class, 'payslip'])->name('entries.payslip');

        Route::get('disputes', [PayrollDisputeController::class, 'index'])->name('disputes.index');
        Route::get('disputes/open', [PayrollDisputeController::class, 'open'])->name('disputes.open');
        Route::post('disputes/{payrollDispute}/resolve', [PayrollDisputeController::class, 'resolve'])->name('disputes.resolve');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('organization', [SettingsController::class, 'organization'])->name('organization.show');
        Route::put('organization', [SettingsController::class, 'updateOrganization'])->name('organization.update');
        Route::get('branding', [SettingsController::class, 'branding'])->name('branding.show');
        Route::post('branding', [SettingsController::class, 'updateBranding'])->name('branding.update');
        Route::get('mail', [SettingsController::class, 'mail'])->name('mail.show');
        Route::put('mail', [SettingsController::class, 'updateMail'])->name('mail.update');
        Route::post('mail/test', [SettingsController::class, 'sendTestMail'])->name('mail.test');
        Route::get('attendance', [SettingsController::class, 'attendance'])->name('attendance.show');
        Route::put('attendance', [SettingsController::class, 'updateAttendance'])->name('attendance.update');
        Route::get('overtime', [SettingsController::class, 'overtime'])->name('overtime.show');
        Route::put('overtime', [SettingsController::class, 'updateOvertime'])->name('overtime.update');
        Route::get('payroll', [SettingsController::class, 'payroll'])->name('payroll.show');
        Route::put('payroll', [SettingsController::class, 'updatePayroll'])->name('payroll.update');
        Route::get('leave', [SettingsController::class, 'leave'])->name('leave.show');
        Route::put('leave', [SettingsController::class, 'updateLeave'])->name('leave.update');
        Route::get('late-penalty-rules', [LatePenaltyRuleController::class, 'index'])->name('late-penalty-rules.show');
        Route::put('late-penalty-rules', [LatePenaltyRuleController::class, 'update'])->name('late-penalty-rules.update');
    });
});
