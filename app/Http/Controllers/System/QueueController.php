<?php

namespace App\Http\Controllers\System;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\QueueInspector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * docs/PRD.md §79 — the Queue page: database-queue depth, the failed-jobs list,
 * and the retry / forget operator actions (each audited).
 */
class QueueController extends Controller
{
    public function __construct(
        private readonly QueueInspector $queue,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('system/queue', [
            'depth' => $this->queue->depth(),
            'failed' => Inertia::defer(fn () => $this->queue->failedJobs(
                max(1, $request->integer('page', 1)),
            )),
        ]);
    }

    public function retry(string $uuid): RedirectResponse
    {
        $result = $this->queue->retry($uuid);

        if ($result['status'] === 'ok') {
            $this->audit->record(AuditAction::QueueJobRetried, reason: "Failed job {$uuid} retried");
        }

        return $this->back($result);
    }

    public function retryAll(): RedirectResponse
    {
        $result = $this->queue->retryAll();

        if ($result['status'] === 'ok' && $result['retried'] > 0) {
            $this->audit->record(AuditAction::QueueJobRetried, reason: "{$result['retried']} failed jobs retried");
        }

        return $this->back($result);
    }

    public function forget(string $uuid): RedirectResponse
    {
        $result = $this->queue->forget($uuid);

        if ($result['status'] === 'ok') {
            $this->audit->record(AuditAction::QueueJobForgotten, reason: "Failed job {$uuid} forgotten");
        }

        return $this->back($result);
    }

    /**
     * @param  array{status: string, message: string}  $result
     */
    private function back(array $result): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => $result['status'] === 'ok' ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }
}
