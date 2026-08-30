<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shifts\SaveShiftRequest;
use App\Http\Resources\Api\V1\ShiftResource;
use App\Models\AttendanceRecord;
use App\Models\EmployeeShift;
use App\Models\OrganizationSettings;
use App\Models\Shift;
use App\Models\ShiftOverride;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
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

    public function destroy(Shift $shift): Response
    {
        abort_unless(Gate::allows('delete', $shift), 403);

        $inUse = EmployeeShift::query()->where('shift_id', $shift->id)->exists()
            || ShiftOverride::query()->where('shift_id', $shift->id)->exists()
            || AttendanceRecord::query()->where('shift_id', $shift->id)->exists()
            || OrganizationSettings::current()->default_shift_id === $shift->id;

        abort_if($inUse, 409, 'This shift is assigned to employees or referenced by attendance history. Deactivate it instead.');

        $shift->delete();

        return response()->noContent();
    }
}
