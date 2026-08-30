<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\CreatePayrollPeriodRequest;
use App\Http\Resources\Api\V1\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * docs/PRD.md §63/§64/§69 — payroll period list / create / show, plus the
 * "HR generates payroll" action that calculates the draft for every
 * employee. Review, finalisation, and locking are Phase 9.
 */
class PayrollPeriodController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PayrollPeriod::class);

        $periods = PayrollPeriod::query()
            ->withCount('entries')
            ->withSum('entries', 'net_salary')
            ->orderByDesc('start_date')
            ->get();

        return ApiResponse::data(PayrollPeriodResource::collection($periods));
    }

    public function show(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('view', $payrollPeriod), 404);

        $payrollPeriod->loadCount('entries')->loadSum('entries', 'net_salary');

        return ApiResponse::data(new PayrollPeriodResource($payrollPeriod));
    }

    public function store(CreatePayrollPeriodRequest $request): JsonResponse
    {
        Gate::authorize('create', PayrollPeriod::class);

        $period = $this->payroll->createPeriod(
            (int) $request->validated('year'),
            (int) $request->validated('month'),
            $request->user(),
        );

        return ApiResponse::data(new PayrollPeriodResource($period), status: 201);
    }

    public function generate(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('generate', $payrollPeriod), 403);

        $result = $this->payroll->generate($payrollPeriod);

        return ApiResponse::data(
            new PayrollPeriodResource($payrollPeriod->fresh()->loadCount('entries')->loadSum('entries', 'net_salary')),
            meta: $result,
        );
    }
}
