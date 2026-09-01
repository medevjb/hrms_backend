<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * docs/PRD.md §79 — proves a queue worker is actually consuming jobs. The
 * scheduler dispatches this every minute; the system health snapshot compares
 * the timestamp it writes against now to decide whether a worker is running.
 *
 * {@see ShouldBeUnique} keeps a backed-up queue from piling these up.
 */
class RecordQueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function handle(): void
    {
        Cache::forever('queue:worker-heartbeat', now()->toIso8601String());
    }
}
