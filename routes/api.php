<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorAuthenticatedSessionController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmployeeController;
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
});
