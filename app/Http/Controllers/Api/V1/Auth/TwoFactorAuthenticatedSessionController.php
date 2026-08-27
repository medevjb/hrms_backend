<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\IssuesSanctumTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\TwoFactorAuthenticationProvider;

class TwoFactorAuthenticatedSessionController extends Controller
{
    use IssuesSanctumTokens;

    /**
     * Complete a login challenge issued by AuthenticatedSessionController::store()
     * with either a TOTP code or a recovery code, and issue a Sanctum token.
     */
    public function store(Request $request, TwoFactorAuthenticationProvider $provider): JsonResponse
    {
        $request->validate([
            'challenge_id' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $cacheKey = "api-2fa-challenge:{$request->string('challenge_id')}";
        $userId = cache()->get($cacheKey);

        if (! $userId) {
            throw ValidationException::withMessages([
                'challenge_id' => 'This two-factor challenge has expired.',
            ]);
        }

        $user = User::query()->whereKey($userId)->firstOrFail();
        $valid = false;

        if ($code = $request->input('code')) {
            $valid = $user->two_factor_secret
                && $provider->verify(decrypt($user->two_factor_secret), $code);
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            if (in_array($recoveryCode, $user->recoveryCodes(), true)) {
                $user->replaceRecoveryCode($recoveryCode);
                $valid = true;
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'The provided two-factor code was invalid.',
            ]);
        }

        cache()->forget($cacheKey);

        return $this->issueTokenResponse($user, $request);
    }
}
