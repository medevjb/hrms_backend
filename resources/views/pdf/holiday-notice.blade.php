{{-- docs/PRD.md §56 — holiday notice PDF: company logo, title, holiday,
     date, message, closure information, return date, Head HR signature,
     generation date. Rendered by HolidayNoticeService via barryvdh/laravel-dompdf. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1f2937; font-size: 12px; line-height: 1.6; margin: 0; padding: 40px; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 28px; }
        .company { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; }
        .reference { color: #6b7280; font-size: 10px; margin-top: 4px; }
        h1 { font-size: 16px; margin: 0 0 20px; }
        .meta { margin-bottom: 24px; }
        .meta div { margin-bottom: 4px; }
        .label { display: inline-block; width: 130px; color: #6b7280; }
        .message { margin: 24px 0; white-space: pre-line; }
        .closure { background: #f9fafb; border: 1px solid #e5e7eb; padding: 14px 16px; margin: 20px 0; }
        .signature { margin-top: 48px; }
        .signature .name { font-weight: bold; border-top: 1px solid #111827; display: inline-block; padding-top: 6px; margin-top: 40px; }
        .footer { margin-top: 40px; color: #9ca3af; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $settings->company_name }}</div>
        <div class="reference">Notice reference: {{ $notice->reference }}</div>
    </div>

    <h1>{{ $notice->title }}</h1>

    <div class="meta">
        <div><span class="label">Holiday</span>{{ $holiday->title }}</div>
        <div><span class="label">Holiday date</span>{{ $holiday->date->isoFormat('dddd, D MMMM YYYY') }}</div>
        @if ($notice->return_date)
            <div><span class="label">Return date</span>{{ $notice->return_date->isoFormat('dddd, D MMMM YYYY') }}</div>
        @endif
        @if ($holiday->office_location)
            <div><span class="label">Applies to</span>{{ $holiday->office_location }}</div>
        @endif
    </div>

    <div class="message">{{ $notice->message }}</div>

    @if ($notice->closure_note)
        <div class="closure">
            <strong>Closure information</strong><br>
            {{ $notice->closure_note }}
        </div>
    @endif

    <div class="signature">
        <div class="name">{{ $notice->signatory_name }}</div><br>
        Head of HR
    </div>

    <div class="footer">
        Generated {{ optional($notice->generated_at)->isoFormat('D MMMM YYYY, HH:mm') }} ·
        {{ $settings->company_name }}
    </div>
</body>
</html>
