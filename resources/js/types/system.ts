export type HealthProbe = {
    status: 'ok' | 'stale' | 'unknown' | 'error';
    [key: string]: unknown;
};

export type HealthSnapshot = {
    application_version: string;
    environment: string;
    laravel_version: string;
    php_version: string;
    database: {
        status: string;
        connection?: string;
        latency_ms?: number;
        message?: string;
    };
    local_storage: { status: string; disk?: string; message?: string };
    scheduler: {
        status: string;
        last_heartbeat: string | null;
        minutes_ago?: number;
    };
    queue: {
        connection: string;
        pending_jobs: number;
        failed_jobs: number;
        worker: {
            status: string;
            last_heartbeat: string | null;
            minutes_ago?: number;
        };
    };
    errors_24h: number;
    checked_at: string;
};

export type ActivityBucket = {
    start: string;
    total: number;
    info: number;
    warning: number;
    error: number;
};

export type TopError = {
    message: string;
    count: number;
    level: string;
    last_seen: string | null;
    explanation: string;
};

export type LogEntry = {
    logged_at: string | null;
    level: string;
    channel: string | null;
    message: string;
    explanation: string | null;
    trace: string | null;
    raw: boolean;
};

export type LogPage = {
    entries: LogEntry[];
    page: number;
    per_page: number;
    has_more: boolean;
    truncated: boolean;
};

export type LogFilters = {
    level: string | null;
    from: string | null;
    to: string | null;
    search: string;
    page: number;
};

export type QueueDepth =
    | { connection: string; available: false }
    | {
          connection: string;
          available: true;
          total_pending: number;
          by_queue: Record<string, number>;
          oldest_pending_age_seconds: number | null;
      };

export type FailedJob = {
    uuid: string;
    connection: string;
    queue: string;
    failed_at: string;
    display_name: string | null;
    exception_summary: string;
    exception: string;
};

export type Paginated<T> = {
    data: T[];
    meta: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
    };
};

export type ScheduledRun = {
    id: number;
    status: 'running' | 'succeeded' | 'failed' | 'skipped' | 'unknown';
    started_at: string;
    finished_at: string | null;
    duration_ms: number | null;
    exit_code: number | null;
    output: string | null;
};

export type ScheduledCommand = {
    command: string;
    expression: string;
    description: string | null;
    next_due_at: string | null;
    without_overlapping: boolean;
    last_run: ScheduledRun | null;
    recent_failures: number;
};

export type ScheduleHistory = Paginated<ScheduledRun> & {
    command: string;
    is_registered: boolean;
};

export type AuditEntry = {
    id: number;
    action: string;
    entity_type: string | null;
    entity_id: number | null;
    old_data: Record<string, unknown> | null;
    new_data: Record<string, unknown> | null;
    reason: string | null;
    ip_address: string | null;
    user: { id: number; name: string } | null;
    created_at: string;
};

export type AuditFilters = {
    action: string | null;
    entity_type: string | null;
    user_id: number | null;
    date_from: string | null;
    date_to: string | null;
};
