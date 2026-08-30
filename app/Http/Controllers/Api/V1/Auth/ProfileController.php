<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data(new ProfileResource($this->withRelations($request->user())));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['name' => $request->validated('name')]);

        $user->employee?->update($request->safe()->only([
            'phone',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
        ]));

        return ApiResponse::data(new ProfileResource($this->withRelations($user->fresh())));
    }

    private function withRelations(User $user): User
    {
        return $user->load('employee.currentTeamMembership.team.department');
    }
}
