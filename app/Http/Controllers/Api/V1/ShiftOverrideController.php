<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shifts\CreateShiftOverrideRequest;
use App\Http\Resources\Api\V1\ShiftOverrideResource;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ShiftOverrideController extends Controller
{
    public function store(CreateShiftOverrideRequest $request): JsonResponse
    {
        Gate::authorize('override', Shift::class);

        $override = ShiftOverride::query()->create([
            ...$request->validated(),
            'changed_by' => $request->user()->id,
        ]);

        return ApiResponse::data(new ShiftOverrideResource($override->load('shift')), status: 201);
    }
}
