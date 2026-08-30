<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    /**
     * Change the signed-in user's password. Per docs/PRD.md §92.2 a
     * password change revokes every one of that user's tokens immediately,
     * so the caller is signed out on success and must log in again.
     */
    public function update(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
        ])->save();

        $user->tokens()->delete();

        return response()->noContent();
    }
}
