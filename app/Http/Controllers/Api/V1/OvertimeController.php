<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Overtime\AdjustOvertimeRequest;
use App\Http\Requests\Api\V1\Overtime\DecideOvertimeRequest;
use App\Http\Resources\Api\V1\OvertimeRecordResource;
use App\Models\OvertimeRecord;
use App\Models\User;
use App\Services\OvertimeService;
use App\Services\ScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §90 — overtime list/detail plus the §50 approval chain and
 * §68 manual adjustment. Detection itself has no endpoint: records are born
 * from the nightly attendance close (OvertimeService::detectForWorkDate()).
 */
class OvertimeController extends Controller
{
    public function __construct(
        private readonly OvertimeService $overtime,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', OvertimeRecord::class);

        $selfId = $request->user()->employee?->id;
        $allowedIds = $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::OvertimeReview);

        $query = OvertimeRecord::query()->with(['employee', 'approvals.approver']);

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

        if ($type = $request->input('filter.type')) {
            $query->where('type', $type);
        }

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->where('work_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('filter.date_to')) {
            $query->where('work_date', '<=', $dateTo);
        }

        $query->orderByDesc('work_date')->orderByDesc('id');

        $records = $query->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => OvertimeRecordResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, OvertimeRecord $overtimeRecord): JsonResponse
    {
        abort_unless(Gate::allows('view', $overtimeRecord), 404);

        $overtimeRecord->load(['employee', 'approvals.approver']);

        return response()->json(['data' => new OvertimeRecordResource($overtimeRecord)]);
    }

    public function approve(DecideOvertimeRequest $request, OvertimeRecord $overtimeRecord): JsonResponse
    {
        abort_unless(Gate::allows('approve', $overtimeRecord), 403);

        $overtimeRecord = $this->overtime->approve($overtimeRecord, $request->user(), $request->validated('reason'));

        return response()->json(['data' => new OvertimeRecordResource($overtimeRecord->load('approvals.approver'))]);
    }

    public function reject(DecideOvertimeRequest $request, OvertimeRecord $overtimeRecord): JsonResponse
    {
        abort_unless(Gate::allows('approve', $overtimeRecord), 403);

        $overtimeRecord = $this->overtime->reject($overtimeRecord, $request->user(), $request->validated('reason'));

        return response()->json(['data' => new OvertimeRecordResource($overtimeRecord->load('approvals.approver'))]);
    }

    public function adjust(AdjustOvertimeRequest $request, OvertimeRecord $overtimeRecord): JsonResponse
    {
        abort_unless(Gate::allows('adjust', $overtimeRecord), 403);

        $overtimeRecord = $this->overtime->adjust(
            $overtimeRecord,
            (float) $request->validated('overtime_days'),
            $request->validated('reason'),
            $request->user(),
        );

        return response()->json(['data' => new OvertimeRecordResource($overtimeRecord->load('approvals.approver'))]);
    }

    /**
     * §50 — TEAM_LEADER/OPERATION_MANAGER stages are person-specific (the
     * record's own team leader / operation manager); HR is role-gated;
     * Head of HR and Admin may act at any pending stage. Mirrors
     * OvertimeRecordPolicy::approve() as a query.
     *
     * @param  Builder<OvertimeRecord>  $query
     */
    private function applyPendingApprovalFilter(Builder $query, User $user): void
    {
        $query->whereNotNull('current_stage')->where(function (Builder $outer) use ($user) {
            $matchedAnyStage = false;

            if ($user->hasRole('Head of HR') || $user->hasRole('Admin')) {
                return; // exceptional authority — any pending stage
            }

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

            if ($user->hasRole('HR')) {
                $matchedAnyStage = true;
                $outer->orWhere('current_stage', 'HR');
            }

            if (! $matchedAnyStage) {
                $outer->whereRaw('1 = 0');
            }
        });
    }
}
