<?php

// docs/PRD.md §79 — tuning knobs for the System Admin / DevOps console.
return [

    'schedule' => [
        // How long a scheduled-task run record is kept before
        // `system:prune-schedule-runs` deletes it.
        'retention_days' => (int) env('SYSTEM_CONSOLE_SCHEDULE_RETENTION_DAYS', 30),

        // A `running` row older than this (hours) is treated as abandoned —
        // the process died before its finish event fired.
        'stale_running_hours' => (int) env('SYSTEM_CONSOLE_SCHEDULE_STALE_RUNNING_HOURS', 6),

        // Bytes of a command's output kept as the stored tail.
        'output_tail_bytes' => 8 * 1024,

        // High-frequency infrastructure commands whose runs are not worth
        // recording. The scheduler's liveness is already covered by the
        // `scheduler:heartbeat` cache key on the Overview page.
        'untracked_commands' => [
            'app:record-scheduler-heartbeat',
            'app:ping-queue-worker',
        ],
    ],

    'logs' => [
        // The file the log viewer reads. Defaults to the single-file channel.
        'channel_path' => env('SYSTEM_CONSOLE_LOG_PATH', storage_path('logs/laravel.log')),

        // The viewer never scans more than this many bytes for one request;
        // beyond it the result is flagged truncated.
        'max_scan_bytes' => (int) env('SYSTEM_CONSOLE_LOG_MAX_SCAN_BYTES', 50 * 1024 * 1024),
    ],

];
