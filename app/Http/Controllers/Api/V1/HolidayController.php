<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Holidays\SaveHolidayRequest;
use App\Http\Resources\Api\V1\HolidayResource;
use App\Models\Holiday;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class HolidayController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Holiday::class);

        $holidays = Holiday::query()->orderBy('date')->get();

        return ApiResponse::data(HolidayResource::collection($holidays));
    }

    public function store(SaveHolidayRequest $request): JsonResponse
    {
        Gate::authorize('create', Holiday::class);

        $holiday = Holiday::query()->create($request->validated());

        return ApiResponse::data(new HolidayResource($holiday), status: 201);
    }

    public function update(SaveHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        Gate::authorize('update', Holiday::class);

        $holiday->update($request->validated());

        return ApiResponse::data(new HolidayResource($holiday->fresh()));
    }

    public function destroy(Holiday $holiday): Response
    {
        Gate::authorize('delete', Holiday::class);

        $holiday->delete();

        return response()->noContent();
    }
}
