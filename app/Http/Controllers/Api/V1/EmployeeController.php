<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\EmployeeStatus;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Employees\AssignShiftRequest;
use App\Http\Requests\Api\V1\Employees\CreateEmployeeRequest;
use App\Http\Requests\Api\V1\Employees\TransferEmployeeRequest;
use App\Http\Requests\Api\V1\Employees\UpdateEmployeeRequest;
use App\Http\Requests\Api\V1\Employees\UpdateEmployeeStatusRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Team;
use App\Services\EmployeeService;
use App\Services\ScopeResolver;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    private const EAGER_LOAD = [
        'user',
        'currentTeamMembership.team.department.operationManager',
        'currentTeamMembership.team.teamLeader',
        'currentShiftAssignment.shift',
    ];

    public function __construct(private readonly ScopeResolver $scopeResolver) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $allowedIds = $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::EmployeeView);

        $query = Employee::query()->with(self::EAGER_LOAD)->orderBy('first_name')->orderBy('last_name');

        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }

        if ($status = $request->query('filter.status')) {
            $query->where('status', $status);
        }

        if ($teamId = $request->query('filter.team_id')) {
            $query->whereHas(
                'currentTeamMembership',
                fn ($q) => $q->where('team_id', $teamId),
            );
        }

        if ($departmentId = $request->query('filter.department_id')) {
            $query->whereHas(
                'currentTeamMembership.team',
                fn ($q) => $q->where('department_id', $departmentId),
            );
        }

        $employees = $query->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => EmployeeResource::collection($employees->items()),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
                'last_page' => $employees->lastPage(),
            ],
        ]);
    }

    public function show(Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('view', $employee), 404);

        return ApiResponse::data(new EmployeeResource($employee->load(self::EAGER_LOAD)));
    }

    public function store(CreateEmployeeRequest $request, EmployeeService $employees): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        $employee = $employees->invite($request->validated(), $request->user());

        return ApiResponse::data(new EmployeeResource($employee->load(self::EAGER_LOAD)), status: 201);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('update', $employee), 404);

        $employee->update($request->validated());

        return ApiResponse::data(new EmployeeResource($employee->fresh(self::EAGER_LOAD)));
    }

    public function updateStatus(
        UpdateEmployeeStatusRequest $request,
        Employee $employee,
        EmployeeService $employees,
    ): JsonResponse {
        abort_unless(Gate::allows('updateStatus', $employee), 404);

        $employees->transitionStatus(
            $employee,
            EmployeeStatus::from($request->validated('status')),
            $request->validated('reason'),
            $request->user(),
        );

        return ApiResponse::data(new EmployeeResource($employee->fresh(self::EAGER_LOAD)));
    }

    public function transfer(
        TransferEmployeeRequest $request,
        Employee $employee,
        EmployeeService $employees,
    ): JsonResponse {
        abort_unless(Gate::allows('update', $employee), 404);

        $team = Team::query()->whereKey($request->validated('team_id'))->firstOrFail();
        $employees->transfer($employee, $team, $request->validated('effective_date'));

        return ApiResponse::data(new EmployeeResource($employee->fresh(self::EAGER_LOAD)));
    }

    public function assignShift(
        AssignShiftRequest $request,
        Employee $employee,
        EmployeeService $employees,
    ): JsonResponse {
        abort_unless(Gate::allows('update', $employee), 404);

        $shift = Shift::query()->whereKey($request->validated('shift_id'))->firstOrFail();
        $employees->assignShift($employee, $shift, $request->validated('effective_date'));

        return ApiResponse::data(new EmployeeResource($employee->fresh(self::EAGER_LOAD)));
    }
}
