<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Leave\SaveLeaveTypeRequest;
use App\Http\Resources\Api\V1\LeaveTypeResource;
use App\Models\LeaveType;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', LeaveType::class);

        $leaveTypes = LeaveType::query()->orderBy('name')->get();

        return ApiResponse::data(LeaveTypeResource::collection($leaveTypes));
    }

    public function store(SaveLeaveTypeRequest $request): JsonResponse
    {
        Gate::authorize('manage', LeaveType::class);

        $leaveType = LeaveType::query()->create($request->validated());

        return ApiResponse::data(new LeaveTypeResource($leaveType), status: 201);
    }

    public function update(SaveLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        Gate::authorize('manage', LeaveType::class);

        $leaveType->update($request->validated());

        return ApiResponse::data(new LeaveTypeResource($leaveType->fresh()));
    }

    public function destroy(LeaveType $leaveType): Response
    {
        Gate::authorize('manage', LeaveType::class);

        $leaveType->update(['is_active' => false]);

        return response()->noContent();
    }
}
