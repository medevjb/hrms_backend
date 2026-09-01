<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PersonalEvents\SavePersonalEventRequest;
use App\Http\Resources\Api\V1\PersonalEventResource;
use App\Models\PersonalEvent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * An employee's own calendar notes (docs/PRD.md §54.1). Every action is
 * scoped to the acting user's paired employee — there is no permission to
 * hold and no way to see or touch anyone else's.
 */
class PersonalEventController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $employeeId = $this->employeeId($request);

        $query = PersonalEvent::query()
            ->where('employee_id', $employeeId)
            ->orderBy('start_date');

        if ($from = $request->input('filter.date_from')) {
            $query->where('end_date', '>=', $from);
        }

        if ($to = $request->input('filter.date_to')) {
            $query->where('start_date', '<=', $to);
        }

        return ApiResponse::data(PersonalEventResource::collection($query->get()));
    }

    public function store(SavePersonalEventRequest $request): JsonResponse
    {
        $event = PersonalEvent::query()->create([
            ...$request->validated(),
            'employee_id' => $this->employeeId($request),
        ]);

        return ApiResponse::data(new PersonalEventResource($event), status: 201);
    }

    public function update(SavePersonalEventRequest $request, PersonalEvent $personalEvent): JsonResponse
    {
        $this->ensureOwned($request, $personalEvent);

        $personalEvent->update($request->validated());

        return ApiResponse::data(new PersonalEventResource($personalEvent->fresh()));
    }

    public function destroy(Request $request, PersonalEvent $personalEvent): Response
    {
        $this->ensureOwned($request, $personalEvent);

        $personalEvent->delete();

        return response()->noContent();
    }

    private function employeeId(Request $request): int
    {
        $id = $request->user()->employee?->id;

        abort_if($id === null, 403, 'Your account is not linked to an employee record.');

        return $id;
    }

    /**
     * Someone else's event is treated as non-existent (§139.2) rather than
     * forbidden, so an ID can't be probed.
     */
    private function ensureOwned(Request $request, PersonalEvent $event): void
    {
        abort_unless($event->employee_id === $request->user()->employee?->id, 404);
    }
}
