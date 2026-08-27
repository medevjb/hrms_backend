<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires docs/PRD.md §11's permission list into Laravel's Gate, so
 * `$user->can('attendance.view')`, `@can('attendance.view')`, and
 * `Gate::authorize('attendance.view')` all work everywhere Laravel expects
 * an ability — without a Gate::define() per permission. Model-scoped
 * authorization (§10's actual scope narrowing) still goes through Policies
 * once the models exist (see UserRolePolicy for the first one).
 */
class PermissionServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            if (PermissionName::tryFrom($ability) === null) {
                return null; // not a permission string — let Policies decide
            }

            return $user->hasPermission($ability);
        });
    }
}
