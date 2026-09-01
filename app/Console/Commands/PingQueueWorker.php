<?php

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Puts one {@see RecordQueueHeartbeat} job on the queue each minute. If a
 * worker is running it lands almost immediately and the /system health check
 * reports the queue worker as alive.
 */
#[Signature('app:ping-queue-worker')]
#[Description('Dispatch a queue heartbeat job so /system can tell if a worker is running')]
class PingQueueWorker extends Command
{
    public function handle(): int
    {
        RecordQueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
