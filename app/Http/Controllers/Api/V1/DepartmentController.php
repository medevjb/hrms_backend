<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Departments\SaveDepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $departments = Department::query()->with('operationManager')->orderBy('name')->get();

        return ApiResponse::data(DepartmentResource::collection($departments));
    }

    public function show(Department $department): JsonResponse
    {
        Gate::authorize('view', Department::class);

        return ApiResponse::data(new DepartmentResource($department->load('operationManager')));
    }

    public function store(SaveDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $department = Department::query()->create($request->validated());

        return ApiResponse::data(new DepartmentResource($department->load('operationManager')), status: 201);
    }

    public function update(SaveDepartmentRequest $request, Department $department): JsonResponse
    {
        Gate::authorize('update', Department::class);

        $department->update($request->validated());

        return ApiResponse::data(new DepartmentResource($department->fresh('operationManager')));
    }
}
