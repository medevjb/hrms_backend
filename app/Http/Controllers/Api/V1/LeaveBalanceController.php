<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Leave\AdjustLeaveBalanceRequest;
use App\Http\Requests\Api\V1\Leave\BulkAdjustLeaveBalanceRequest;
use App\Http\Resources\Api\V1\LeaveBalanceResource;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\OrganizationSettings;
use App\Services\LeaveBalanceService;
use App\Services\ScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class LeaveBalanceController extends Controller
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    /**
     * Defaults to the caller's own balances; an explicit employee_id filter
     * needs leave.review or leave.balance.adjust scope over that employee.
     */
    public function index(Request $request): JsonResponse
    {
        $selfId = $request->user()->employee?->id;
        $requestedEmployeeId = $request->integer('filter.employee_id') ?: $selfId;

        abort_unless($requestedEmployeeId !== null, 404);

        if ($requestedEmployeeId !== $selfId) {
            $allowedIds = $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::LeaveReview)
                ?? $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::LeaveBalanceAdjust);

            abort_unless($allowedIds === null || in_array($requestedEmployeeId, $allowedIds, true), 404);
        }

        $settings = OrganizationSettings::current();
        $leaveYear = $request->integer('filter.leave_year')
            ?: $this->balances->leaveYearFor(Carbon::now(), $settings->leave_year_start_month);

        $employee = Employee::query()->findOrFail($requestedEmployeeId);
        $leaveTypes = LeaveType::query()->where('is_active', true)->get();

        $balances = $leaveTypes->map(fn ($leaveType) => $this->balances->balanceFor($employee, $leaveType, $leaveYear));

        return response()->json(['data' => LeaveBalanceResource::collection($balances)]);
    }

    /**
     * §37 org-wide balance operation — grant/set/reset a leave type's
     * balance for every active employee at once. Policy-manager only
     * (Admin / Head of HR); HR's per-employee `leave.balance.adjust` does
     * not reach this.
     */
    public function bulkAdjust(BulkAdjustLeaveBalanceRequest $request): JsonResponse
    {
        Gate::authorize('manage', LeaveType::class);

        $leaveType = LeaveType::query()->findOrFail($request->integer('leave_type_id'));

        $affected = $this->balances->bulkApply(
            $leaveType,
            $request->validated('mode'),
            $request->has('amount') && $request->validated('amount') !== null
                ? (float) $request->validated('amount')
                : null,
            $request->validated('note'),
            $request->user(),
        );

        return response()->json(['data' => ['affected' => $affected]]);
    }

    public function adjust(AdjustLeaveBalanceRequest $request, LeaveBalance $leaveBalance): JsonResponse
    {
        abort_unless(Gate::allows('adjust', $leaveBalance), 404);

        $balance = $this->balances->adjust(
            $leaveBalance,
            (float) $request->validated('amount'),
            $request->validated('note'),
            $request->user(),
        );

        return response()->json(['data' => new LeaveBalanceResource($balance)]);
    }
}
