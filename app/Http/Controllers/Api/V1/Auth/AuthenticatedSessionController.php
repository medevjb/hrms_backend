<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\IssuesSanctumTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticatedSessionController extends Controller
{
    use IssuesSanctumTokens;

    /**
     * Authenticate the given credentials and issue a Sanctum token, or,
     * if the account has two-factor authentication enabled, return a
     * challenge id for POST /api/v1/auth/two-factor-challenge.
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                Fortify::username() => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                Fortify::username() => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $challengeId = (string) Str::uuid();

            cache()->put("api-2fa-challenge:{$challengeId}", $user->id, now()->addMinutes(5));

            return response()->json([
                'two_factor' => true,
                'challenge_id' => $challengeId,
            ], 202);
        }

        return $this->issueTokenResponse($user, $request);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data(new UserResource($request->user()));
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->input(Fortify::username())).'|'.$request->ip()
        );
    }
}
