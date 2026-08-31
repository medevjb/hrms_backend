<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Services\ScheduleInspector;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/PRD.md §79 — the Schedule page: every registered command with its cron
 * expression, next due time, and recorded run history.
 */
class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleInspector $schedule) {}

    public function index(): Response
    {
        return Inertia::render('system/schedule', [
            'commands' => $this->schedule->commands(),
        ]);
    }

    public function show(Request $request, string $command): Response
    {
        return Inertia::render('system/schedule-history', [
            'history' => $this->schedule->history($command, max(1, $request->integer('page', 1))),
        ]);
    }
}
