<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\LogReader;
use App\Support\Logs\LogLevel;
use App\Support\Logs\LogQuery;
use App\Support\Logs\LogScrollMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/PRD.md §79 — the searchable log viewer. Filter state travels in the
 * query string; entries stream in via infinite scroll, and the 24-hour
 * activity and top-errors summaries are deferred.
 */
class LogController extends Controller
{
    public function __construct(private readonly LogReader $logs) {}

    public function index(Request $request): Response
    {
        $query = LogQuery::fromRequest($request);

        return Inertia::render('system/logs', [
            'filters' => [
                'level' => $query->minLevel,
                'from' => $query->from?->toIso8601String(),
                'to' => $query->to?->toIso8601String(),
                'search' => $query->search,
                'page' => $query->page,
            ],
            'levels' => LogLevel::names(),
            'result' => Inertia::scroll(
                fn () => $this->logs->query($query)->toArray(),
                wrapper: 'entries',
                metadata: fn (array $page) => new LogScrollMetadata($page),
            ),
            'activity' => Inertia::defer(fn () => $this->logs->histogram(Carbon::now()->subDay())),
            'topErrors' => Inertia::defer(fn () => $this->logs->topErrors(Carbon::now()->subDay())),
        ]);
    }
}
