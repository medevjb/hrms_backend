<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\LogReader;
use App\Services\SystemHealthService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/PRD.md §79 — the console overview: the health snapshot as status tiles,
 * with the heavier top-errors summary deferred.
 */
class OverviewController extends Controller
{
    public function __construct(
        private readonly SystemHealthService $health,
        private readonly LogReader $logs,
    ) {}

    public function show(): Response
    {
        return Inertia::render('system/overview', [
            'health' => $this->health->snapshot(),
            'activity' => Inertia::defer(fn () => $this->logs->histogram(Carbon::now()->subDay())),
            'topErrors' => Inertia::defer(fn () => $this->logs->topErrors(Carbon::now()->subDay())),
        ]);
    }
}
