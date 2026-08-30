<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PayrollDisputeResolution;
use App\Enums\PayrollDisputeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\ResolveDisputeRequest;
use App\Http\Resources\Api\V1\PayrollDisputeResource;
use App\Models\PayrollDispute;
use App\Services\PayrollWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §147 — the HR-side dispute queue: list open/resolved
 * disputes and record a resolution with its mandatory explanation.
 */
class PayrollDisputeController extends Controller
{
    public function __construct(private readonly PayrollWorkflowService $workflow) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollDispute::class);

        $query = PayrollDispute::query()->with(['entry.employee', 'entry.period'])->latest('id');

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        return ApiResponse::data(PayrollDisputeResource::collection($query->get()));
    }

    public function resolve(ResolveDisputeRequest $request, PayrollDispute $payrollDispute): JsonResponse
    {
        abort_unless(Gate::allows('resolve', $payrollDispute), 403);

        $dispute = $this->workflow->resolveDispute(
            $payrollDispute,
            $request->user(),
            PayrollDisputeResolution::from($request->validated('resolution')),
            $request->validated('note'),
        );

        return ApiResponse::data(new PayrollDisputeResource($dispute->load(['entry.employee', 'entry.period'])));
    }

    public function open(): JsonResponse
    {
        Gate::authorize('viewAny', PayrollDispute::class);

        $count = PayrollDispute::query()->where('status', PayrollDisputeStatus::Open)->count();

        return ApiResponse::data(['open' => $count]);
    }
}
