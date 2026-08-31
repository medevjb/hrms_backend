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
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $query = Employee::query()->with(self::EAGER_LOAD);

        if ($allowedIds !== null) {
            $query->whereIn('id', $allowedIds);
        }

        // input(), not query(): Symfony's ParameterBag::get() (what query()
        // calls) has no dot-notation support, so 'filter.status' against a
        // real ?filter[status]=X request always returned null here — silent
        // no-op filters, never caught because no test sent a bracket query
        // string. input() goes through data_get(), which does support it.
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        if ($employmentType = $request->input('filter.employment_type')) {
            $query->where('employment_type', $employmentType);
        }

        if ($teamId = $request->input('filter.team_id')) {
            $query->whereHas(
                'currentTeamMembership',
                fn ($q) => $q->where('team_id', $teamId),
            );
        }

        if ($departmentId = $request->input('filter.department_id')) {
            $query->whereHas(
                'currentTeamMembership.team',
                fn ($q) => $q->where('department_id', $departmentId),
            );
        }

        if ($teamLeaderId = $request->input('filter.team_leader_id')) {
            $query->whereHas(
                'currentTeamMembership.team',
                fn ($q) => $q->where('team_leader_id', $teamLeaderId),
            );
        }

        if ($operationManagerId = $request->input('filter.operation_manager_id')) {
            $query->whereHas(
                'currentTeamMembership.team.department',
                fn ($q) => $q->where('operation_manager_id', $operationManagerId),
            );
        }

        if ($shiftId = $request->input('filter.shift_id')) {
            $query->whereHas(
                'currentShiftAssignment',
                fn ($q) => $q->where('shift_id', $shiftId),
            );
        }

        if ($request->has('filter.overtime_eligible')) {
            $query->where('overtime_eligible', $request->boolean('filter.overtime_eligible'));
        }

        if ($request->boolean('filter.unassigned')) {
            $query->whereDoesntHave('currentTeamMembership');
        }

        if ($joinedFrom = $request->input('filter.joined_from')) {
            $query->whereDate('joining_date', '>=', $joinedFrom);
        }

        if ($joinedTo = $request->input('filter.joined_to')) {
            $query->whereDate('joining_date', '<=', $joinedTo);
        }

        if ($search = trim((string) $request->input('filter.search', ''))) {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('employee_code', 'like', $like)
                    ->orWhere('designation', 'like', $like)
                    ->orWhereRaw("concat(first_name, ' ', last_name) like ?", [$like])
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like));
            });
        }

        [$sortColumn, $sortDirection] = match ($request->input('sort')) {
            'name_desc' => ['first_name', 'desc'],
            'joined' => ['joining_date', 'asc'],
            'joined_desc' => ['joining_date', 'desc'],
            'code' => ['employee_code', 'asc'],
            'code_desc' => ['employee_code', 'desc'],
            default => ['first_name', 'asc'],
        };
        $query->orderBy($sortColumn, $sortDirection);
        if ($sortColumn === 'first_name') {
            $query->orderBy('last_name', $sortDirection);
        }

        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));
        $employees = $query->paginate($perPage);

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

    public function photo(Employee $employee): StreamedResponse
    {
        abort_unless(Gate::allows('view', $employee), 404);
        abort_if(
            $employee->profile_image_path === null
                || ! Storage::disk('local')->exists($employee->profile_image_path),
            404,
        );

        return Storage::disk('local')->response($employee->profile_image_path);
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

    public function destroy(Employee $employee, EmployeeService $employees): Response
    {
        abort_unless(Gate::allows('delete', $employee), 404);

        $employees->delete($employee, request()->user());

        return response()->noContent();
    }

    public function resendInvitation(Employee $employee, EmployeeService $employees): Response
    {
        abort_unless(Gate::allows('update', $employee), 404);

        $employees->resendInvitation($employee);

        return response()->noContent();
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
