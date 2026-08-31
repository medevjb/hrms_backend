<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdateProfilePhotoRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\ProfileResource;
use App\Models\Employee;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data(new ProfileResource($this->withRelations($request->user())));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if ($employee !== null) {
            $employee->update($request->safe()->only([
                'first_name',
                'last_name',
                'phone',
                'address',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]));

            // The account name always mirrors the employee's real name.
            $user->update(['name' => $employee->fresh()->fullName()]);
        } elseif ($request->filled('name')) {
            $user->update(['name' => $request->validated('name')]);
        }

        return ApiResponse::data(new ProfileResource($this->withRelations($user->fresh())));
    }

    public function updatePhoto(UpdateProfilePhotoRequest $request): JsonResponse
    {
        $employee = $this->requireEmployee($request);
        $file = $request->file('photo');

        $this->deletePhotoFile($employee);

        $employee->update([
            'profile_image_path' => $file->storeAs(
                "avatars/{$employee->id}",
                Str::uuid().'.'.$file->getClientOriginalExtension(),
                'local',
            ),
        ]);

        return ApiResponse::data(new ProfileResource($this->withRelations($request->user()->fresh())));
    }

    public function deletePhoto(Request $request): JsonResponse
    {
        $employee = $this->requireEmployee($request);

        $this->deletePhotoFile($employee);
        $employee->update(['profile_image_path' => null]);

        return ApiResponse::data(new ProfileResource($this->withRelations($request->user()->fresh())));
    }

    public function showPhoto(Request $request): StreamedResponse
    {
        $path = $request->user()->employee?->profile_image_path;

        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    private function requireEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if($employee === null, 409, 'Only an employee record can have a photo.');

        return $employee;
    }

    private function deletePhotoFile(Employee $employee): void
    {
        if ($employee->profile_image_path !== null) {
            Storage::disk('local')->delete($employee->profile_image_path);
        }
    }

    private function withRelations(User $user): User
    {
        return $user->load([
            'employee.currentTeamMembership.team.department.operationManager',
            'employee.currentTeamMembership.team.teamLeader',
            'employee.currentShiftAssignment.shift',
        ]);
    }
}
