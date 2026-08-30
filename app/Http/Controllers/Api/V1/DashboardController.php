<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/PRD.md §73–§78, §91 — one role-aware dashboard payload. The widgets
 * present depend on the caller's permissions; DashboardService does the
 * assembly and scoping.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::data($this->dashboard->for($request->user()));
    }
}
