<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;

/**
 * docs/PRD.md §83 — `audit.view` is read-only by construction; there is no
 * create / update / delete ability, here or anywhere.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionName::AuditView);
    }
}
