<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Enums\SalaryComponentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\SaveSalaryComponentRequest;
use App\Http\Resources\Api\V1\SalaryComponentResource;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryComponent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * docs/PRD.md §59 — the salary component catalogue. Anyone who can see or
 * set a salary reads the list; editing the catalogue itself needs
 * `payroll.settings.manage`.
 */
class SalaryComponentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission(PermissionName::EmployeeFinancialView)
                || $request->user()->hasPermission(PermissionName::PayrollView)
                || $request->user()->hasPermission(PermissionName::PayrollSettingsManage),
            403,
        );

        $components = SalaryComponent::query()->orderBy('sort_order')->get();

        return ApiResponse::data(SalaryComponentResource::collection($components));
    }

    public function store(SaveSalaryComponentRequest $request): JsonResponse
    {
        $this->authorizeManage($request);

        $component = SalaryComponent::query()->create($request->validated());

        return ApiResponse::data(new SalaryComponentResource($component), status: 201);
    }

    public function update(SaveSalaryComponentRequest $request, SalaryComponent $salaryComponent): JsonResponse
    {
        $this->authorizeManage($request);

        $salaryComponent->update($request->validated());

        return ApiResponse::data(new SalaryComponentResource($salaryComponent->fresh()));
    }

    public function destroy(Request $request, SalaryComponent $salaryComponent): Response
    {
        $this->authorizeManage($request);

        abort_if(
            $salaryComponent->type === SalaryComponentType::Basic,
            409,
            'The Basic Salary component is required and cannot be deleted.',
        );

        abort_if(
            EmployeeSalaryComponent::query()->where('salary_component_id', $salaryComponent->id)->exists(),
            409,
            'This component is used in one or more employee salaries. Deactivate it instead.',
        );

        $salaryComponent->delete();

        return response()->noContent();
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission(PermissionName::PayrollSettingsManage), 403);
    }
}
