<?php

namespace App\Enums;

/**
 * docs/PRD.md §79 — the outcome of one scheduled-command run, as recorded by
 * ScheduledTaskRunSubscriber from the framework's scheduler events.
 */
enum ScheduledTaskStatus: string
{
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';

    /**
     * A run left `running` past `system-console.schedule.stale_running_hours` —
     * the process died before its finish event fired.
     */
    case Unknown = 'unknown';
}
