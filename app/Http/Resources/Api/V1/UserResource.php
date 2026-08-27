<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'two_factor_enabled' => $this->hasEnabledTwoFactorAuthentication(),
            // Display-only, per docs/PRD.md §92.2 — the frontend hides/shows
            // controls with these, but every real check happens server-side.
            'roles' => $this->roles()->pluck('name')->values(),
            'permissions' => $this->permissionNames(),
        ];
    }
}
