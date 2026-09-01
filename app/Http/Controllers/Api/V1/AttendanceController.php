<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttendanceSource;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\AdjustAttendanceRequest;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Http\Resources\Api\V1\AttendanceTodayResource;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\OrganizationSettings;
use App\Services\AttendanceService;
use App\Services\ReportingPeriodService;
use App\Services\ScopeResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function today(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        return ApiResponse::data(new AttendanceTodayResource($this->attendance->today($employee)));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $record = $this->attendance->checkIn($employee, $request->user(), AttendanceSource::Web);

        return ApiResponse::data(new AttendanceRecordResource($record), status: 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $this->currentEmployee($request);

        $record = $this->attendance->checkOut($employee, $request->user(), AttendanceSource::Web);

        return ApiResponse::data(new AttendanceRecordResource($record));
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $allowedIds = $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::AttendanceView);

        $query = AttendanceRecord::query()
            ->with(['employee', 'shift'])
            ->orderByDesc('work_date');

        if ($allowedIds !== null) {
            $query->whereIn('employee_id', $allowedIds);
        }

        if ($employeeId = $request->input('filter.employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        if ($date = $request->input('filter.date')) {
            $query->where('work_date', $date);
        }

        // §85 — `filter[period]=YYYY-MM` scopes to that reporting month
        // without the caller repeating the boundary math. Explicit
        // date_from / date_to below still narrow further.
        if ($period = $request->input('filter.period')) {
            $resolved = app(ReportingPeriodService::class)->forKey(
                (string) $period,
                OrganizationSettings::current()->reporting_month_cutoff_day,
            );
            $query->whereBetween('work_date', [
                $resolved->startDate->toDateString(),
                $resolved->endDate->toDateString(),
            ]);
        }

        if ($dateFrom = $request->input('filter.date_from')) {
            $query->where('work_date', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('filter.date_to')) {
            $query->where('work_date', '<=', $dateTo);
        }

        if ($shiftId = $request->input('filter.shift_id')) {
            $query->where('shift_id', $shiftId);
        }

        if ($teamId = $request->input('filter.team_id')) {
            $query->whereHas(
                'employee.currentTeamMembership',
                fn ($q) => $q->where('team_id', $teamId),
            );
        }

        if ($departmentId = $request->input('filter.department_id')) {
            $query->whereHas(
                'employee.currentTeamMembership.team',
                fn ($q) => $q->where('department_id', $departmentId),
            );
        }

        if ($teamLeaderId = $request->input('filter.team_leader_id')) {
            $query->whereHas(
                'employee.currentTeamMembership.team',
                fn ($q) => $q->where('team_leader_id', $teamLeaderId),
            );
        }

        if ($operationManagerId = $request->input('filter.operation_manager_id')) {
            $query->whereHas(
                'employee.currentTeamMembership.team.department',
                fn ($q) => $q->where('operation_manager_id', $operationManagerId),
            );
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        } elseif ($request->boolean('filter.late')) {
            $query->where('status', 'LATE');
        } elseif ($request->boolean('filter.absent')) {
            $query->where('status', 'ABSENT');
        } elseif ($request->boolean('filter.missing_checkout')) {
            $query->where('status', 'MISSING_CHECKOUT');
        }

        $records = $query->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => AttendanceRecordResource::collection($records->items()),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function adjust(AdjustAttendanceRequest $request, AttendanceRecord $attendanceRecord): JsonResponse
    {
        abort_unless(Gate::allows('correct', $attendanceRecord), 404);

        $changes = $request->only(['check_in', 'check_out', 'status']);

        $record = $this->attendance->adjust(
            $attendanceRecord,
            $changes,
            $request->validated('reason'),
            $request->user(),
        );

        return ApiResponse::data(new AttendanceRecordResource($record));
    }

    private function currentEmployee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_unless($employee !== null, 404, 'No employee profile is associated with this account.');

        return $employee;
    }
}
