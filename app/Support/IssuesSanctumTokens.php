<?php

namespace App\Support;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait IssuesSanctumTokens
{
    protected function issueTokenResponse(User $user, Request $request): JsonResponse
    {
        $deviceName = (string) $request->input('device_name', 'unknown-device');
        $token = $user->createToken($deviceName)->plainTextToken;
        $expirationMinutes = config('sanctum.expiration');

        return ApiResponse::data([
            'token' => $token,
            'user' => new UserResource($user),
            'expires_at' => $expirationMinutes
                ? now()->addMinutes((int) $expirationMinutes)->toIso8601String()
                : null,
        ]);
    }
}
