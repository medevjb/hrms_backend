<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shifts\SaveShiftRequest;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\Shift;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShiftController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Shift::class);

        $shifts = Shift::query()->orderBy('name')->get();

        return ApiResponse::data(ShiftResource::collection($shifts));
    }

    public function store(SaveShiftRequest $request): JsonResponse
    {
        Gate::authorize('create', Shift::class);

        $shift = Shift::query()->create($request->validated());

        return ApiResponse::data(new ShiftResource($shift), status: 201);
    }

    public function update(SaveShiftRequest $request, Shift $shift): JsonResponse
    {
        Gate::authorize('update', Shift::class);

        $shift->update($request->validated());

        return ApiResponse::data(new ShiftResource($shift->fresh()));
    }
}
