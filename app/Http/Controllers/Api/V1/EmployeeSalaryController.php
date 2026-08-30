<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\AssignSalaryRequest;
use App\Http\Resources\Api\V1\EmployeeSalaryResource;
use App\Models\Employee;
use App\Services\SalaryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §12/§59 — an employee's current salary plus its full
 * effective-dated history. `employee.financial.*` gated and scoped; an
 * employee may always read their own (§12).
 */
class EmployeeSalaryController extends Controller
{
    public function __construct(private readonly SalaryService $salaries) {}

    public function show(Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('viewSalary', $employee), 404);

        $history = $employee->salaries()
            ->with('components.component')
            ->orderByDesc('effective_from')
            ->get();

        return ApiResponse::data([
            'current' => $history->firstWhere('ended_at', null)
                ? new EmployeeSalaryResource($history->firstWhere('ended_at', null))
                : null,
            'history' => EmployeeSalaryResource::collection($history),
        ]);
    }

    public function update(AssignSalaryRequest $request, Employee $employee): JsonResponse
    {
        abort_unless(Gate::allows('manageSalary', $employee), Gate::allows('viewSalary', $employee) ? 403 : 404);

        /** @var list<array{salary_component_id: int|string, amount: int|string}> $componentInput */
        $componentInput = $request->validated('components');
        $componentAmounts = [];

        foreach ($componentInput as $component) {
            $componentAmounts[(int) $component['salary_component_id']] = (string) $component['amount'];
        }

        $salary = $this->salaries->assign(
            $employee,
            Carbon::parse($request->validated('effective_from')),
            $componentAmounts,
            $request->validated('note'),
            $request->user(),
        );

        return ApiResponse::data(new EmployeeSalaryResource($salary), status: 201);
    }
}
