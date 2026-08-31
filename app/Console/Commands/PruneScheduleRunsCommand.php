<?php

namespace App\Console\Commands;

use App\Enums\ScheduledTaskStatus;
use App\Models\ScheduledTaskRun;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * docs/PRD.md §79 — keeps `scheduled_task_runs` bounded: deletes runs past the
 * retention window and reclassifies `running` rows whose process died before
 * their finish event fired. Scheduled daily (routes/console.php).
 */
#[Signature('system:prune-schedule-runs')]
#[Description('Prune old scheduled-task run records and mark abandoned runs')]
class PruneScheduleRunsCommand extends Command
{
    public function handle(): int
    {
        $retentionDays = (int) config('system-console.schedule.retention_days');
        $staleHours = (int) config('system-console.schedule.stale_running_hours');

        $stale = ScheduledTaskRun::query()
            ->where('status', ScheduledTaskStatus::Running)
            ->where('started_at', '<', Carbon::now()->subHours($staleHours))
            ->update([
                'status' => ScheduledTaskStatus::Unknown,
                'finished_at' => Carbon::now(),
            ]);

        $deleted = ScheduledTaskRun::query()
            ->where('created_at', '<', Carbon::now()->subDays($retentionDays))
            ->delete();

        $this->components->info("Schedule runs: {$deleted} pruned, {$stale} marked abandoned.");

        return self::SUCCESS;
    }
}
