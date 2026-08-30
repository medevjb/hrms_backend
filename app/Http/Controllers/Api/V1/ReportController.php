<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Enums\PermissionName;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Reports\BuildReportRequest;
use App\Services\AuditLogger;
use App\Services\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * docs/PRD.md §99, §11, §91 — the V1 reports. `report.view` for the JSON
 * preview, `report.export` for the CSV (§11 — payroll data leaving the
 * system). Every row is scoped to what the caller may see (§10).
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function types(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(PermissionName::ReportView), 403);

        return ApiResponse::data(
            collect(ReportType::cases())->map(fn (ReportType $type) => [
                'type' => $type->value,
                'title' => $type->title(),
                'uses_payroll_period' => $type->usesPayrollPeriod(),
            ]),
        );
    }

    public function show(BuildReportRequest $request, string $type): JsonResponse
    {
        abort_unless($request->user()->hasPermission(PermissionName::ReportView), 403);

        $report = $this->reports->build($this->resolveType($type), $request->filters(), $request->user());

        $preview = array_slice($report->rows, 0, 100);

        return ApiResponse::data([
            'type' => $report->type->value,
            'title' => $report->type->title(),
            'columns' => $report->columns,
            'rows' => $preview,
            'total' => count($report->rows),
            'truncated' => count($report->rows) > count($preview),
        ]);
    }

    public function export(BuildReportRequest $request, string $type): StreamedResponse
    {
        abort_unless($request->user()->hasPermission(PermissionName::ReportExport), 403);

        $reportType = $this->resolveType($type);
        $report = $this->reports->build($reportType, $request->filters(), $request->user());
        $filename = $report->type->value.'-'.now()->format('Y-m-d').'.csv';

        app(AuditLogger::class)->record(
            AuditAction::ReportExported, null,
            newData: ['report' => $reportType->value, 'rows' => count($report->rows), 'filters' => $request->filters()],
        );

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $report->headerRow());
            foreach ($report->bodyRows() as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resolveType(string $type): ReportType
    {
        return ReportType::tryFrom($type) ?? abort(404, "Unknown report type '{$type}'.");
    }
}
