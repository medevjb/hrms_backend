<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Proves the scheduler is actually running. The §79 system health endpoint
 * (Phase 12) reads this cache key rather than trusting cron blindly.
 */
#[Signature('app:record-scheduler-heartbeat')]
#[Description('Record that the scheduler is alive, for the /system health check')]
class RecordSchedulerHeartbeat extends Command
{
    public function handle(): void
    {
        Cache::forever('scheduler:heartbeat', now()->toIso8601String());
    }
}
