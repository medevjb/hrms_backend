<?php

namespace App\Http\Controllers\System;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/PRD.md §79 / §83 — the audit log, browsable in the console. Read-only:
 * the model itself refuses writes, and this controller exposes no mutation.
 */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'action' => $request->string('action')->toString() ?: null,
            'entity_type' => $request->string('entity_type')->toString() ?: null,
            'user_id' => $request->integer('user_id') ?: null,
            'date_from' => $request->date('date_from')?->toDateString(),
            'date_to' => $request->date('date_to')?->toDateString(),
        ];

        $logs = AuditLog::query()
            ->with('user')
            ->latest('id')
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action))
            ->when($filters['entity_type'], fn ($q, $type) => $q->where('entity_type', 'like', "%\\{$type}"))
            ->when($filters['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['date_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->paginate(min(100, max(1, $request->integer('per_page', 50))))
            ->withQueryString();

        return Inertia::render('system/audit', [
            'filters' => $filters,
            'actions' => array_map(fn (AuditAction $a) => $a->value, AuditAction::cases()),
            'logs' => [
                'data' => AuditLogResource::collection($logs->items())->resolve($request),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ],
        ]);
    }
}
