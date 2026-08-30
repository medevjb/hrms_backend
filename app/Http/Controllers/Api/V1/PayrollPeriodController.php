<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\CreateArrearRequest;
use App\Http\Requests\Api\V1\Payroll\CreatePayrollPeriodRequest;
use App\Http\Resources\Api\V1\PayrollArrearResource;
use App\Http\Resources\Api\V1\PayrollPeriodResource;
use App\Http\Resources\Api\V1\PayrollRunResource;
use App\Models\Employee;
use App\Models\PayrollArrear;
use App\Models\PayrollPeriod;
use App\Services\ArrearService;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use App\Support\ApiResponse;
use App\Support\Money;
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
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly PayrollWorkflowService $workflow,
        private readonly ArrearService $arrears,
    ) {}

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

    public function review(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('advance', $payrollPeriod), 403);

        return $this->periodResponse($this->workflow->moveToReview($payrollPeriod));
    }

    public function release(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('advance', $payrollPeriod), 403);

        return $this->periodResponse($this->workflow->release($payrollPeriod));
    }

    public function finalize(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('finalize', $payrollPeriod), 403);

        return $this->periodResponse($this->workflow->finalize($payrollPeriod, $request->user()));
    }

    public function markPaid(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('finalize', $payrollPeriod), 403);

        return $this->periodResponse($this->workflow->markPaid($payrollPeriod));
    }

    public function lock(Request $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('finalize', $payrollPeriod), 403);

        return $this->periodResponse($this->workflow->lock($payrollPeriod));
    }

    public function runs(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('view', $payrollPeriod), 404);

        return ApiResponse::data(
            PayrollRunResource::collection($payrollPeriod->payrollRuns()->orderByDesc('sequence')->get()),
        );
    }

    public function arrears(PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('view', $payrollPeriod), 404);

        $arrears = PayrollArrear::query()
            ->where('original_period_id', $payrollPeriod->id)
            ->orWhere('target_period_id', $payrollPeriod->id)
            ->with(['employee', 'originalPeriod'])
            ->latest('id')
            ->get();

        return ApiResponse::data(PayrollArrearResource::collection($arrears));
    }

    public function storeArrear(CreateArrearRequest $request, PayrollPeriod $payrollPeriod): JsonResponse
    {
        abort_unless(Gate::allows('createArrear', $payrollPeriod), 403);

        $arrear = $this->arrears->openManualArrear(
            Employee::query()->findOrFail((int) $request->validated('employee_id')),
            PayrollPeriod::query()->findOrFail((int) $request->validated('original_period_id')),
            Money::round((string) $request->validated('amount')),
            $request->validated('reason'),
            $request->user(),
        );

        return ApiResponse::data(
            new PayrollArrearResource($arrear->load(['employee', 'originalPeriod'])),
            status: 201,
        );
    }

    private function periodResponse(PayrollPeriod $period): JsonResponse
    {
        return ApiResponse::data(
            new PayrollPeriodResource($period->loadCount('entries')->loadSum('entries', 'net_salary')),
        );
    }
}
