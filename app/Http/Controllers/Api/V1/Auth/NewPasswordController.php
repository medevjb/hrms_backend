<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\EmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class NewPasswordController extends Controller
{
    /**
     * Also doubles as "accept my invitation" — an invited employee setting
     * their first password IS accepting the invite, there's no separate
     * step (docs/PRD.md §148 #2). The token may have come from either the
     * ordinary 60-minute password-reset broker or the 72-hour
     * employee_invitations one; both share a table, so try the default
     * first and fall back rather than requiring the caller to know which.
     */
    public function store(
        ResetPasswordRequest $request,
        ResetsUserPasswords $resetsUserPasswords,
        EmployeeService $employees,
    ): JsonResponse {
        $credentials = $request->only('email', 'password', 'password_confirmation', 'token');

        $callback = function (User $user) use ($request, $resetsUserPasswords, $employees) {
            $resetsUserPasswords->reset($user, $request->only('password', 'password_confirmation'));

            // docs/PRD.md §92.2 — a password change revokes every token,
            // not just the current session's.
            $user->tokens()->delete();

            if ($user->employee?->status === EmployeeStatus::Invited) {
                $employees->acceptInvitation($user->employee);
            }
        };

        $status = Password::broker('users')->reset($credentials, $callback);

        if ($status === Password::INVALID_TOKEN) {
            $status = Password::broker('employee_invitations')->reset($credentials, $callback);
        }

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return ApiResponse::data(['message' => 'Password reset successfully.']);
    }
}
