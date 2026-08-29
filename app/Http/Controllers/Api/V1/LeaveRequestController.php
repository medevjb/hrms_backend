<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\HalfDayPeriod;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Leave\DecideLeaveRequestRequest;
use App\Http\Requests\Api\V1\Leave\SubmitLeaveRequestRequest;
use App\Http\Resources\Api\V1\LeaveRequestResource;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveService $leave,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $selfId = $request->user()->employee?->id;
        $allowedIds = $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::LeaveReview);

        $query = LeaveRequest::query()->with(['employee', 'leaveType', 'approvals.approver']);

        if ($allowedIds !== null) {
            $ids = $selfId !== null ? array_unique([...$allowedIds, $selfId]) : $allowedIds;
            $query->whereIn('employee_id', $ids);
        }

        if ($employeeId = $request->input('filter.employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($request->boolean('filter.mine')) {
            $query->where('employee_id', $selfId);
        }

        if ($request->boolean('filter.pending_my_approval')) {
            $this->applyPendingApprovalFilter($query, $request->user());
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($leaveTypeId = $request->input('filter.leave_type_id')) {
            $query->where('leave_type_id', $leaveTypeId);
        }

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->where('end_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('filter.date_to')) {
            $query->where('start_date', '<=', $dateTo);
        }

        $query->orderByDesc('submitted_at');

        $requests = $query->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => LeaveRequestResource::collection($requests->items()),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
                'last_page' => $requests->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless(Gate::allows('view', $leaveRequest), 404);

        $leaveRequest->load(['employee', 'leaveType', 'approvals.approver']);

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)]);
    }

    public function store(SubmitLeaveRequestRequest $submitRequest): JsonResponse
    {
        Gate::authorize('create', LeaveRequest::class);

        $employee = $this->currentEmployee($submitRequest);
        $leaveType = LeaveType::query()->findOrFail((int) $submitRequest->validated('leave_type_id'));

        $leaveRequest = $this->leave->submit(
            $employee,
            $leaveType,
            Carbon::parse($submitRequest->validated('start_date')),
            Carbon::parse($submitRequest->validated('end_date')),
            $submitRequest->boolean('is_half_day'),
            $submitRequest->validated('half_day_period') ? HalfDayPeriod::from($submitRequest->validated('half_day_period')) : null,
            $submitRequest->validated('reason'),
        );

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)], 201);
    }

    public function approve(DecideLeaveRequestRequest $decideRequest, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless(Gate::allows('approve', $leaveRequest), 403);

        $leaveRequest = $this->leave->approve($leaveRequest, $decideRequest->user(), $decideRequest->validated('reason'));

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)]);
    }

    public function reject(DecideLeaveRequestRequest $decideRequest, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless(Gate::allows('approve', $leaveRequest), 403);

        $leaveRequest = $this->leave->reject($leaveRequest, $decideRequest->user(), $decideRequest->validated('reason'));

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)]);
    }

    public function directApprove(DecideLeaveRequestRequest $decideRequest, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless(Gate::allows('directApprove', $leaveRequest), 403);

        $leaveRequest = $this->leave->directApprove($leaveRequest, $decideRequest->user(), $decideRequest->validated('reason'));

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)]);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless(Gate::allows('cancel', $leaveRequest), 403);

        $leaveRequest = $this->leave->cancel($leaveRequest, $request->user());

        return response()->json(['data' => new LeaveRequestResource($leaveRequest)]);
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_unless($employee !== null, 404, 'No employee profile is associated with this account.');

        return $employee;
    }

    /**
     * §41 — each stage has its own eligibility rule: TEAM_LEADER/
     * OPERATION_MANAGER are person-specific (the request's own team leader
     * or operation manager), while HR/HEAD_HR/ADMIN are role-gated and
     * org-wide. Mirrors LeaveRequestPolicy::approve()'s per-stage checks,
     * expressed as a query instead of evaluated per-record.
     */
    /**
     * @param  Builder<LeaveRequest>  $query
     */
    private function applyPendingApprovalFilter(Builder $query, User $user): void
    {
        $query->where(function (Builder $outer) use ($user) {
            $matchedAnyStage = false;

            if ($user->hasRole('Team Leader')) {
                $matchedAnyStage = true;
                $outer->orWhere(fn (Builder $q) => $q->where('current_stage', 'TEAM_LEADER')
                    ->whereHas('employee.currentTeamMembership.team', fn ($t) => $t->where('team_leader_id', $user->employee?->id)));
            }

            if ($user->hasRole('Operation Manager')) {
                $matchedAnyStage = true;
                $outer->orWhere(fn (Builder $q) => $q->where('current_stage', 'OPERATION_MANAGER')
                    ->whereHas('employee.currentTeamMembership.team.department', fn ($d) => $d->where('operation_manager_id', $user->employee?->id)));
            }

            if ($user->hasRole('HR') || $user->hasRole('Head of HR') || $user->hasRole('Admin')) {
                $matchedAnyStage = true;
                $outer->orWhere('current_stage', 'HR');
            }

            if ($user->hasRole('Head of HR') || $user->hasRole('Admin')) {
                $matchedAnyStage = true;
                $outer->orWhere('current_stage', 'HEAD_HR');
            }

            if ($user->hasRole('Admin')) {
                $matchedAnyStage = true;
                $outer->orWhere('current_stage', 'ADMIN');
            }

            if (! $matchedAnyStage) {
                $outer->whereRaw('1 = 0');
            }
        });
    }
}
