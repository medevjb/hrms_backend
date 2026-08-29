<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorAuthenticatedSessionController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\LeaveBalanceController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\OvertimeController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\ShiftOverrideController;
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
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/{user}/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
    Route::post('users/{user}/roles', [UserRoleController::class, 'store'])->name('users.roles.store');
    Route::delete('users/{user}/roles/{userRole}', [UserRoleController::class, 'destroy'])
        ->name('users.roles.destroy');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::patch('employees/{employee}/status', [EmployeeController::class, 'updateStatus'])
        ->name('employees.update-status');
    Route::post('employees/{employee}/transfer', [EmployeeController::class, 'transfer'])
        ->name('employees.transfer');
    Route::post('employees/{employee}/assign-shift', [EmployeeController::class, 'assignShift'])
        ->name('employees.assign-shift');

    Route::get('departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::get('departments/{department}', [DepartmentController::class, 'show'])->name('departments.show');
    Route::put('departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

    Route::get('teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('teams/{team}', [TeamController::class, 'show'])->name('teams.show');
    Route::put('teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::get('teams/{team}/members', [TeamController::class, 'members'])->name('teams.members.index');
    Route::post('teams/{team}/members', [TeamController::class, 'addMember'])->name('teams.members.store');
    Route::delete('teams/{team}/members/{employee}', [TeamController::class, 'removeMember'])
        ->name('teams.members.destroy');

    Route::get('shifts', [ShiftController::class, 'index'])->name('shifts.index');
    Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
    Route::put('shifts/{shift}', [ShiftController::class, 'update'])->name('shifts.update');
    Route::post('shift-overrides', [ShiftOverrideController::class, 'store'])->name('shift-overrides.store');

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

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

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('organization', [SettingsController::class, 'organization'])->name('organization.show');
        Route::put('organization', [SettingsController::class, 'updateOrganization'])->name('organization.update');
        Route::get('attendance', [SettingsController::class, 'attendance'])->name('attendance.show');
        Route::put('attendance', [SettingsController::class, 'updateAttendance'])->name('attendance.update');
        Route::get('overtime', [SettingsController::class, 'overtime'])->name('overtime.show');
        Route::put('overtime', [SettingsController::class, 'updateOvertime'])->name('overtime.update');
        Route::get('payroll', [SettingsController::class, 'payroll'])->name('payroll.show');
        Route::put('payroll', [SettingsController::class, 'updatePayroll'])->name('payroll.update');
        Route::get('leave', [SettingsController::class, 'leave'])->name('leave.show');
        Route::put('leave', [SettingsController::class, 'updateLeave'])->name('leave.update');
    });
});
