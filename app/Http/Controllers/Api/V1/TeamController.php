<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Teams\AddTeamMemberRequest;
use App\Http\Requests\Api\V1\Teams\SaveTeamRequest;
use App\Http\Resources\Api\V1\TeamMemberResource;
use App\Http\Resources\Api\V1\TeamResource;
use App\Models\Employee;
use App\Models\Team;
use App\Services\EmployeeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Team::class);

        $query = Team::query()->with(['department', 'teamLeader'])->withCount('currentTeamMembers')->orderBy('name');

        if ($departmentId = $request->query('filter.department_id')) {
            $query->where('department_id', $departmentId);
        }

        $teams = $query->get();

        return ApiResponse::data(TeamResource::collection($teams));
    }

    public function show(Team $team): JsonResponse
    {
        Gate::authorize('view', Team::class);

        return ApiResponse::data(new TeamResource(
            $team->load(['department', 'teamLeader'])->loadCount('currentTeamMembers'),
        ));
    }

    public function store(SaveTeamRequest $request): JsonResponse
    {
        Gate::authorize('create', Team::class);

        $team = Team::query()->create($request->validated());

        return ApiResponse::data(
            new TeamResource($team->load(['department', 'teamLeader'])->loadCount('currentTeamMembers')),
            status: 201,
        );
    }

    public function update(SaveTeamRequest $request, Team $team): JsonResponse
    {
        Gate::authorize('update', Team::class);

        $team->update($request->validated());

        return ApiResponse::data(
            new TeamResource($team->fresh(['department', 'teamLeader'])->loadCount('currentTeamMembers')),
        );
    }

    public function members(Team $team): JsonResponse
    {
        Gate::authorize('view', Team::class);

        $members = $team->currentTeamMembers()->with('employee')->get();

        return ApiResponse::data(TeamMemberResource::collection($members));
    }

    public function addMember(AddTeamMemberRequest $request, Team $team, EmployeeService $employees): JsonResponse
    {
        Gate::authorize('manageMembers', Team::class);

        $employee = Employee::query()->whereKey($request->validated('employee_id'))->firstOrFail();
        $employees->transfer($employee, $team, $request->validated('effective_date'));

        return ApiResponse::data(
            new TeamMemberResource($employee->currentTeamMembership()->with('employee')->firstOrFail()),
            status: 201,
        );
    }

    public function removeMember(Team $team, Employee $employee, EmployeeService $employees): Response
    {
        Gate::authorize('manageMembers', Team::class);

        abort_unless($employee->currentTeam()?->id === $team->id, 404);

        $employees->removeFromTeam($employee);

        return response()->noContent();
    }
}
