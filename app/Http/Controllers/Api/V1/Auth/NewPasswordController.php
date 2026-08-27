<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class NewPasswordController extends Controller
{
    public function store(ResetPasswordRequest $request, ResetsUserPasswords $resetsUserPasswords): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request, $resetsUserPasswords) {
                $resetsUserPasswords->reset($user, $request->only('password', 'password_confirmation'));

                // docs/PRD.md §92.2 — a password change revokes every token,
                // not just the current session's.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        }

        return ApiResponse::data(['message' => 'Password reset successfully.']);
    }
}
