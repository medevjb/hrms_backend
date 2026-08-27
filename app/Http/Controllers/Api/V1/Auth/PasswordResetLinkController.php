<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    /**
     * Always responds with a generic success message, whether or not the
     * email belongs to an account — revealing that would let an attacker
     * enumerate registered emails. The password broker's own throttle
     * (config/auth.php: 60s per email) rate-limits repeated sends.
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return ApiResponse::data([
            'message' => 'If that email address is registered, a password reset link has been sent.',
        ]);
    }
}
