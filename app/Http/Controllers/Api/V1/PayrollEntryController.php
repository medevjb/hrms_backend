<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\PayrollAdjustmentType;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Payroll\AdjustPayrollEntryRequest;
use App\Http\Requests\Api\V1\Payroll\RaiseDisputeRequest;
use App\Http\Resources\Api\V1\PayrollDisputeResource;
use App\Http\Resources\Api\V1\PayrollEntryResource;
use App\Models\PayrollAdjustment;
use App\Models\PayrollEntry;
use App\Services\AuditLogger;
use App\Services\PayrollService;
use App\Services\PayrollWorkflowService;
use App\Services\ScopeResolver;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/PRD.md §66/§68/§70 — payroll entry list / detail and the §68 manual
 * adjustment. An employee sees only their own entries; a reviewer sees
 * entries for employees inside their `payroll.view` scope.
 */
class PayrollEntryController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
        private readonly PayrollWorkflowService $workflow,
        private readonly ScopeResolver $scopeResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PayrollEntry::class);

        $selfId = $request->user()->employee?->id;
        $allowedIds = $request->user()->hasPermission(PermissionName::PayrollView)
            ? $this->scopeResolver->employeeIdsFor($request->user(), PermissionName::PayrollView)
            : [];

        $query = PayrollEntry::query()->with(['employee', 'period']);

        if ($allowedIds !== null) {
            $ids = $selfId !== null ? array_values(array_unique([...$allowedIds, $selfId])) : $allowedIds;
            $query->whereIn('employee_id', $ids);
        }

        if ($periodId = $request->input('filter.payroll_period_id')) {
            $query->where('payroll_period_id', $periodId);
        }

        if ($request->boolean('filter.mine')) {
            $query->where('employee_id', $selfId);
        }

        $entries = $query->orderByDesc('id')->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => PayrollEntryResource::collection($entries->items()),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
                'last_page' => $entries->lastPage(),
            ],
        ]);
    }

    public function show(PayrollEntry $payrollEntry): JsonResponse
    {
        abort_unless(Gate::allows('view', $payrollEntry), 404);

        $payrollEntry->load(['employee', 'period', 'lines', 'adjustments', 'disputes', 'payslip']);

        return ApiResponse::data(new PayrollEntryResource($payrollEntry));
    }

    public function acknowledge(Request $request, PayrollEntry $payrollEntry): JsonResponse
    {
        abort_unless(Gate::allows('respond', $payrollEntry), 404);

        $entry = $this->workflow->acknowledge($payrollEntry, $request->user());

        return ApiResponse::data(new PayrollEntryResource($entry->load(['employee', 'period'])));
    }

    public function dispute(RaiseDisputeRequest $request, PayrollEntry $payrollEntry): JsonResponse
    {
        abort_unless(Gate::allows('respond', $payrollEntry), 404);

        $dispute = $this->workflow->raiseDispute($payrollEntry, $request->user(), $request->validated('reason'));

        return ApiResponse::data(new PayrollDisputeResource($dispute), status: 201);
    }

    public function payslip(PayrollEntry $payrollEntry): StreamedResponse
    {
        abort_unless(Gate::allows('viewPayslip', $payrollEntry), 404);

        $payslip = $payrollEntry->payslip;
        abort_if($payslip === null, 404, 'No payslip has been generated for this entry yet.');

        return Storage::disk('local')->download($payslip->file_path, "{$payslip->reference}.pdf");
    }

    public function adjust(AdjustPayrollEntryRequest $request, PayrollEntry $payrollEntry): JsonResponse
    {
        abort_unless(Gate::allows('adjust', $payrollEntry), 403);
        abort_if($payrollEntry->period->status->isClosed(), 409, 'This payroll period is closed.');

        $type = PayrollAdjustmentType::from($request->validated('type'));
        $isEarning = $type->lineType()->category()->value === 'EARNING';

        $adjustment = PayrollAdjustment::query()->create([
            'payroll_entry_id' => $payrollEntry->id,
            'type' => $type,
            'label' => $request->validated('label'),
            'amount' => Money::round((string) $request->validated('amount')),
            'reason' => $request->validated('reason'),
            'previous_value' => $isEarning ? $payrollEntry->gross_earnings : $payrollEntry->total_deductions,
            'created_by_user_id' => $request->user()->id,
        ]);

        $entry = $this->payroll->adjust($payrollEntry, $adjustment);

        $adjustment->update([
            'new_value' => $isEarning ? $entry->gross_earnings : $entry->total_deductions,
        ]);

        app(AuditLogger::class)->record(
            AuditAction::PayrollAdjusted, $payrollEntry,
            oldData: ['value' => (string) $adjustment->previous_value],
            newData: ['type' => $type->value, 'amount' => (string) $adjustment->amount, 'value' => (string) $adjustment->new_value],
            reason: $request->validated('reason'),
        );

        return ApiResponse::data(
            new PayrollEntryResource($entry->load(['employee', 'period', 'lines', 'adjustments'])),
        );
    }
}
