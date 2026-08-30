{{-- docs/PRD.md §71 — payslip: company, employee, period, itemised
     earnings and deductions, gross, deductions, net, payment status.
     Rendered by PayrollWorkflowService via barryvdh/laravel-dompdf. --}}
@php
    $earnings = $entry->lines->where('category', \App\Enums\PayrollLineCategory::Earning);
    $deductions = $entry->lines->where('category', \App\Enums\PayrollLineCategory::Deduction);
    $fmt = fn ($v) => number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 12px; margin: 0; padding: 36px; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #111827; padding-bottom: 12px; margin-bottom: 20px; }
        .company { font-size: 17px; font-weight: bold; }
        .muted { color: #6b7280; font-size: 10px; }
        h1 { font-size: 14px; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { text-align: left; font-size: 10px; color: #6b7280; text-transform: uppercase; padding: 4px 0; border-bottom: 1px solid #e5e7eb; }
        td { padding: 5px 0; border-bottom: 1px solid #f3f4f6; }
        td.amount { text-align: right; font-family: monospace; }
        .totals td { border: none; padding: 3px 0; }
        .totals .net { font-size: 14px; font-weight: bold; border-top: 2px solid #111827; padding-top: 6px; }
        .grid { width: 100%; margin-bottom: 18px; }
        .grid td { border: none; padding: 2px 0; }
        .label { color: #6b7280; width: 130px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="company">{{ $settings->company_name }}</div>
            <div class="muted">Payslip {{ $reference }}</div>
        </div>
        <div class="muted" style="text-align:right">
            Generated {{ now()->isoFormat('D MMMM YYYY') }}
        </div>
    </div>

    <h1>Payslip — {{ $period->label }}</h1>

    <table class="grid">
        <tr><td class="label">Employee</td><td>{{ $employee->fullName() }} ({{ $employee->employee_code }})</td></tr>
        <tr><td class="label">Designation</td><td>{{ $employee->designation }}</td></tr>
        <tr><td class="label">Period</td><td>{{ $period->start_date->toDateString() }} – {{ $period->end_date->toDateString() }}</td></tr>
        <tr><td class="label">Payment status</td><td>{{ str_replace('_', ' ', $period->status->value) }}</td></tr>
        <tr><td class="label">Days / daily salary</td><td>{{ $entry->period_days }} / {{ $fmt($entry->daily_salary) }}</td></tr>
    </table>

    <table>
        <thead><tr><th>Earnings</th><th style="text-align:right">Amount</th></tr></thead>
        <tbody>
        @foreach ($earnings as $line)
            <tr><td>{{ $line->label }}</td><td class="amount">{{ $fmt($line->amount) }}</td></tr>
        @endforeach
        </tbody>
    </table>

    @if ($deductions->isNotEmpty())
        <table>
            <thead><tr><th>Deductions</th><th style="text-align:right">Amount</th></tr></thead>
            <tbody>
            @foreach ($deductions as $line)
                <tr><td>{{ $line->label }}</td><td class="amount">{{ $fmt($line->amount) }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <table class="totals" style="width:50%; margin-left:50%">
        <tr><td>Gross earnings</td><td class="amount">{{ $fmt($entry->gross_earnings) }}</td></tr>
        <tr><td>Total deductions</td><td class="amount">{{ $fmt($entry->total_deductions) }}</td></tr>
        <tr class="net"><td>Net salary</td><td class="amount">{{ $fmt($entry->net_salary) }}</td></tr>
    </table>
</body>
</html>
