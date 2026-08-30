<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Services\SystemHealthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/PRD.md §79/§91 — the minimal technical health snapshot, gated on
 * `system.health.view` (the /system console's permission, exposed here so
 * a DevOps tool can poll it).
 */
class SystemHealthController extends Controller
{
    public function __construct(private readonly SystemHealthService $health) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(PermissionName::SystemHealthView), 403);

        return ApiResponse::data($this->health->snapshot());
    }
}
