## Why

The `/system` Inertia console is the PRD's designated home (§5.1, §79) for the
System Admin / DevOps dashboard, but today it is a single placeholder page with
no real authorization gate. A DevOps engineer has no first-class place to audit
sensitive actions, read application errors, or confirm that the queue and the
scheduler are actually doing their work — the only signal is a coarse
`GET /api/v1/system/health` snapshot meant for polling, not for a person
diagnosing an incident. This change turns the console into a usable operations
surface without adding any infrastructure the PRD rules out (no Redis / Horizon /
Pulse, no broadcasting, no request-traffic metrics).

## What Changes

- **BREAKING** (internal): every `/system` route is gated behind the
  `system.health.view` permission instead of the current `auth` + `verified`
  placeholder. A signed-in user without that permission can no longer reach the
  console.
- The console gains a navigation shell and five pages:
  - **Overview** — the health snapshot (app / Laravel / PHP version, environment,
    database probe + latency, local-storage probe, scheduler freshness, queue
    depth, failed-job count, 24h error count) rendered as status tiles.
  - **Logs** — a searchable viewer over `storage/logs/laravel.log`: filter by
    level and time range, full-text search, paginated entries, expandable stack
    traces, and a "top errors" summary that groups recent entries by message
    with counts and last-seen.
  - **Queue** — pending jobs by queue with the oldest-pending age, and the
    failed-jobs list (uuid, queue, exception summary, failed-at) with expandable
    exception detail. Retry-one, retry-all, and forget-one actions.
  - **Schedule** — every registered scheduled command with its cron expression,
    next due time, last run (started, duration, exit code, output tail), and
    recent run history, backed by new per-run persistence.
  - **Audit** — the existing audit log (`audit_logs`) browsable in the console:
    filter by action, entity type, actor, and date; paginated; read-only.
- New backend capability: scheduled-task run tracking. A listener records one
  row per scheduled-command run (start, finish, duration, exit code, output,
  failure) via the framework's schedule events; the existing
  `app:record-scheduler-heartbeat` cache heartbeat stays as the liveness signal.
- New backend capability: an application-log reader service that parses the
  Monolog "single" file into structured, filterable entries.
- New backend capability: queue inspection + failed-job actions (retry / forget)
  exposed to the console.
- `SystemHealthService` gains the 24h error count sourced from the log reader;
  its shape is otherwise unchanged so `GET /api/v1/system/health` keeps working.

## Capabilities

### New Capabilities

- `system-console`: the session-authenticated `/system` Inertia console — its
  authorization gate (`system.health.view`), navigation shell, health-overview
  dashboard, and the audit-log viewer page.
- `scheduled-task-monitoring`: per-run persistence and history for scheduled
  console commands (start / finish / duration / exit code / output / failure),
  and the derived "last run" and "recent failures" views.
- `application-log-viewer`: reading the application log file into structured
  entries with level, time-range, and full-text filtering, pagination, stack
  traces, and a grouped top-errors summary.
- `queue-monitoring`: inspecting database-queue depth and age, listing failed
  jobs with exception detail, and retrying or forgetting them.

### Modified Capabilities

<!-- No existing openspec specs; all behavior here is new. -->

## Impact

- **Routes:** `routes/web.php` — `/system` group re-gated and expanded with the
  new pages and their data/action endpoints (session-authenticated, CSRF-
  protected, per §3026 / §3118). No change to `/api/v1/*`.
- **Frontend:** new Inertia React pages under `resources/js/pages/system/`
  (overview, logs, queue, schedule, audit) plus a shared console layout and
  sidebar; Wayfinder-generated route helpers for the `/system` console.
- **Database:** one new table for scheduled-task runs. Reads from existing
  `jobs` / `failed_jobs` tables; no schema change there.
- **Backend:** new services (log reader, queue inspector, schedule-run
  recorder), a schedule-event listener registered in a service provider,
  Inertia controllers under `app/Http/Controllers/System/`, `SystemHealthService`
  extended with the error count.
- **Permissions:** no new `PermissionName` case — `system.health.view` already
  exists and is held by System Admin (§113). Its meaning widens from "read the
  health snapshot" to "reach the DevOps console".
- **Docs:** `docs/PRD.md` §79 / §113 notes and `docs/api.md` updated to reflect
  the console pages (documentation edits happen during implementation only if the
  user asks).
- **Out of scope:** websockets / broadcasting health, HTTP request-traffic
  metrics, Redis / Horizon / Pulse, any HR-facing page, log retention or
  shipping configuration.
