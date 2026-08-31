<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\Role;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only view of the fixed V1 role catalogue (docs/PRD.md §8, §11) and the
 * permissions each role carries. Assigning a role to a person — and picking
 * that grant's scope — is UserRoleController's job; this is the reference map.
 */
class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        // id order mirrors the seed order, which is the org hierarchy
        // (Admin, Head of HR, HR, Operation Manager, ...) — more useful here
        // than alphabetical.
        $roles = Role::query()->with('permissions')->orderBy('id')->get();

        return ApiResponse::data(RoleResource::collection($roles));
    }

    public function show(Role $role): JsonResponse
    {
        Gate::authorize('view', Role::class);

        return ApiResponse::data(new RoleResource($role->load('permissions')));
    }
}
