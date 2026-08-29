<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Settings\UpdateAttendanceSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateLeaveSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateOrganizationSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdateOvertimeSettingsRequest;
use App\Http\Requests\Api\V1\Settings\UpdatePayrollSettingsRequest;
use App\Http\Resources\Api\V1\AttendanceSettingsResource;
use App\Http\Resources\Api\V1\LeaveSettingsResource;
use App\Http\Resources\Api\V1\OrganizationSettingsResource;
use App\Http\Resources\Api\V1\OvertimeSettingsResource;
use App\Http\Resources\Api\V1\PayrollSettingsResource;
use App\Models\OrganizationSettings;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Four differently-gated views over the same OrganizationSettings singleton
 * (docs/PRD.md §139.6, §85) — see OrganizationSettingsPolicy for why each
 * group has its own permission.
 */
class SettingsController extends Controller
{
    public function organization(): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        return ApiResponse::data(new OrganizationSettingsResource(OrganizationSettings::current()));
    }

    public function updateOrganization(UpdateOrganizationSettingsRequest $request): JsonResponse
    {
        Gate::authorize('organization', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new OrganizationSettingsResource($settings->fresh()));
    }

    public function attendance(): JsonResponse
    {
        Gate::authorize('attendance', OrganizationSettings::class);

        return ApiResponse::data(new AttendanceSettingsResource(OrganizationSettings::current()));
    }

    public function updateAttendance(UpdateAttendanceSettingsRequest $request): JsonResponse
    {
        Gate::authorize('attendance', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new AttendanceSettingsResource($settings->fresh()));
    }

    public function overtime(): JsonResponse
    {
        Gate::authorize('overtime', OrganizationSettings::class);

        return ApiResponse::data(new OvertimeSettingsResource(OrganizationSettings::current()));
    }

    public function updateOvertime(UpdateOvertimeSettingsRequest $request): JsonResponse
    {
        Gate::authorize('overtime', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new OvertimeSettingsResource($settings->fresh()));
    }

    public function payroll(): JsonResponse
    {
        Gate::authorize('payroll', OrganizationSettings::class);

        return ApiResponse::data(new PayrollSettingsResource(OrganizationSettings::current()));
    }

    public function updatePayroll(UpdatePayrollSettingsRequest $request): JsonResponse
    {
        Gate::authorize('payroll', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new PayrollSettingsResource($settings->fresh()));
    }

    public function leave(): JsonResponse
    {
        Gate::authorize('leave', OrganizationSettings::class);

        return ApiResponse::data(new LeaveSettingsResource(OrganizationSettings::current()));
    }

    public function updateLeave(UpdateLeaveSettingsRequest $request): JsonResponse
    {
        Gate::authorize('leave', OrganizationSettings::class);

        $settings = OrganizationSettings::current();
        $settings->update($request->validated());

        return ApiResponse::data(new LeaveSettingsResource($settings->fresh()));
    }
}
