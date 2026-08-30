<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §83 — the read-only audit viewer. `audit.view` gated; there
 * is deliberately no write endpoint.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('user')->latest('id');

        if ($action = $request->input('filter.action')) {
            $query->where('action', $action);
        }

        if ($entityType = $request->input('filter.entity_type')) {
            $query->where('entity_type', 'like', "%\\\\{$entityType}");
        }

        if ($userId = $request->input('filter.user_id')) {
            $query->where('user_id', $userId);
        }

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('filter.date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $logs = $query->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'data' => AuditLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }
}
