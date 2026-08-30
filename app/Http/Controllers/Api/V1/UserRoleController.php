<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Users\AssignRoleRequest;
use App\Http\Resources\Api\V1\UserRoleResource;
use App\Models\User;
use App\Models\UserRole;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserRoleController extends Controller
{
    public function index(User $user): JsonResponse
    {
        Gate::authorize('viewAny', UserRole::class);

        return ApiResponse::data(
            UserRoleResource::collection($user->roleAssignments()->with('role')->get()),
        );
    }

    public function store(AssignRoleRequest $request, User $user): JsonResponse
    {
        Gate::authorize('create', UserRole::class);

        $userRole = $user->roleAssignments()->create($request->validated());

        app(AuditLogger::class)->record(
            AuditAction::RoleAssigned, $user, newData: $request->validated(),
        );

        return ApiResponse::data(new UserRoleResource($userRole->load('role')), status: 201);
    }

    public function destroy(User $user, UserRole $userRole): Response
    {
        Gate::authorize('delete', $userRole);

        abort_unless($userRole->user_id === $user->id, 404);

        $userRole->delete();

        return response()->noContent();
    }
}
