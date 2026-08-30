<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalaryComponentResource;
use App\Models\SalaryComponent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * docs/PRD.md §59 — the salary component catalogue. Read-only over the API
 * for V1 (the five §59 components are seeded); anyone who can see or set a
 * salary needs the list.
 */
class SalaryComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(PermissionName::EmployeeFinancialView)
                || $request->user()->hasPermission(PermissionName::PayrollView),
            403,
        );

        $components = SalaryComponent::query()->where('is_active', true)->orderBy('sort_order')->get();

        return ApiResponse::data(SalaryComponentResource::collection($components));
    }
}
