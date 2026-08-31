## Context

See `proposal.md` — Why. Current state relevant to the approach:

- The `/system` route group in `routes/web.php` is `['auth', 'verified']` with a
  `TODO(Phase 1)` to gate it behind `system.health.view`. `PermissionServiceProvider`
  already registers a `Gate::before` that resolves any `PermissionName` string
  (including `system.health.view`) through `User::hasPermission()`, so Laravel's
  `can:` route middleware works today with no new wiring.
- `SystemHealthService::snapshot()` returns the fields §79 lists except
  "Recent Errors". `GET /api/v1/system/health` (gated on `system.health.view`)
  wraps it for polling. This endpoint must keep working unchanged.
- `AuditLogController` + `AuditLogPolicy` (`audit.view`) already expose
  `GET /api/v1/audit-logs` with action / entity / user / date filters and
  pagination. `AuditLog` is append-only, enforced in `AuditLog::booted()`.
- Queue connection is `database`; `jobs` and `failed_jobs` tables exist.
  Cache/session are `database`. Log channel is `stack → single`, one file at
  `storage/logs/laravel.log` (the `daily` channel exists but is not the default).
- The scheduler runs `app:record-scheduler-heartbeat` every minute plus four
  daily/hourly domain commands (`attendance:close`, `leave:rollover`,
  `holidays:scan-notices`, `announcements:publish-due`).
- Frontend: Inertia v3 + React, pages in `resources/js/pages/system/`, layouts in
  `resources/js/layouts/`, an `app-sidebar` / `nav-main` pattern already used by
  the settings pages. Wayfinder generates route helpers; `@/routes` is imported
  by the existing dashboard page.
- `docs/PRD.md` §79 bans Redis / Horizon / Pulse; there is no broadcasting
  config file and `broadcasting.default` is `log`.

## Goals / Non-Goals

**Goals:**

- One authorization boundary for the whole console: `can:system.health.view` on
  the `/system` group, replacing the placeholder, covering pages and their
  data/action endpoints alike.
- Read models that are cheap and safe to call on every page load: log parsing
  streamed from the tail, queue counts as aggregate queries, schedule state from
  a small indexed table.
- Reuse existing building blocks — `SystemHealthService`, the audit query,
  Laravel's own `queue:retry` / `queue:forget` internals, the framework's
  scheduler events — rather than reimplementing them.
- Keep `GET /api/v1/system/health` and `GET /api/v1/audit-logs` behaviourally
  unchanged; the console consumes the same data through Inertia props.

**Non-Goals:**

- No new `PermissionName` case; no per-page granularity below `system.health.view`.
- No log shipping, retention policy, or rotation change; the viewer reads
  whatever the `single` channel wrote.
- No live-streaming / websockets on any page — refresh is a manual reload or a
  bounded Inertia poll.
- No queue worker supervision, no `jobs` (pending) mutation — only `failed_jobs`
  retry/forget.
- No REST/`/api/v1` surface for logs, queue, or schedule; those live only under
  the session-authenticated `/system` group.

## Decisions

### 1. Authorization: `can:` middleware on the route group

Replace `['auth', 'verified']` with `['auth', 'verified', 'can:system.health.view']`
on the `/system` group. The `Gate::before` in `PermissionServiceProvider` already
maps the ability string, so no policy or gate definition is needed. Every new
data and action route is declared inside that group and inherits the check.

- *Alternative — a dedicated middleware class:* redundant given `Gate::before`.
- *Alternative — policy on a `SystemConsole` pseudo-model:* more indirection for
  a single boolean.

Web session + CSRF already apply to `routes/web.php` (§3118), so the retry/forget
POSTs are CSRF-protected with no extra work.

### 2. Scheduled-task runs: listen on framework scheduler events

Add a `scheduled_task_runs` table and a listener subscribed to
`ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed`, and
`ScheduledTaskSkipped`. On *starting*, open a row (command, started_at). On
*finished/failed/skipped*, close it (finished_at, duration_ms, exit_code,
status, output tail from `$event->task->output` when it is a file, else the
buffered output). Register the subscriber in a service provider.

Table shape:

| column        | type         | notes                                   |
|---------------|--------------|-----------------------------------------|
| id            | big int pk   |                                         |
| command       | string idx   | `$event->task->command` or description  |
| status        | string       | `running` / `succeeded` / `failed` / `skipped` |
| started_at    | timestamp    |                                         |
| finished_at   | timestamp?   |                                         |
| duration_ms   | unsigned int?|                                         |
| exit_code     | small int?   |                                         |
| output        | text?        | bounded tail (e.g. last 8 KB)           |
| created_at    | timestamp    | for pruning                             |

- *Why events over wrapping each `Schedule::command()` call:* the events fire for
  every task including ones added later; no per-command boilerplate; skipped and
  overlap-guarded runs are reported by the framework itself.
- *Retention:* a `model:prune`-style command (or `->prune()` on the model via the
  scheduler) deletes rows older than a config value (`system-console.schedule.retention_days`,
  default 30). Added as one more `Schedule::command()` line.
- *"Registered commands" list on the page:* read from
  `app(Schedule::class)->events()` at request time for cron expression + next-due;
  left-join the latest run per command from the table.

- *Alternative — parse `schedule:list` output:* fragile, no timing or outcome.
- *Alternative — Horizon/Pulse:* banned by §79.

### 3. Log viewer: a reverse-chunked file reader, not a DB index

`LogReader` opens `storage/logs/laravel.log` and reads it in chunks from the end,
splitting on the Monolog line prefix (`[YYYY-MM-DD HH:MM:SS] channel.LEVEL:`).
Each match starts a new entry; following lines (stack trace) attach to it. It
yields entries newest-first and stops once it has filled the requested page or
walked past the time-range floor. A line that never matches the prefix while no
entry is open becomes a `raw` entry.

- Filtering (level ≥ X, time range, substring search over message+trace) is
  applied while scanning, so a page returns after reading only as much of the
  file as needed.
- `checked_at`/24h-error-count for the Overview: a lighter pass that counts
  `ERROR`/`CRITICAL`/`ALERT`/`EMERGENCY` prefixes with a timestamp within 24h.
  Exposed as `LogReader::errorCountSince(CarbonInterface)` and called by
  `SystemHealthService` — the only change to that service, appended to the
  snapshot array as `errors_24h`.
- Top-errors summary: over a recent window (default 24h), group by message with
  volatile fragments masked (UUIDs, integers, quoted paths → placeholders),
  count, keep max level and last-seen.
- Missing file → empty result, no throw.

- *Alternative — ingest logs into a table via a Monolog handler:* write
  amplification on every request, schema for something the filesystem already
  stores, divergence from what ops see over SSH. Rejected.
- *Alternative — pull in a log-viewer package:* new dependency (needs approval),
  more surface than four filters and a summary need.
- *Constraint:* assumes the default single-file channel. If deployment switches
  to `daily`, the reader globs `laravel-*.log` newest-first — noted as a small
  follow-up, not built now.

### 4. Queue monitoring: aggregate queries + framework retry internals

- Depth: `SELECT queue, COUNT(*) ... GROUP BY queue` and `MIN(created_at)` on
  `jobs` (its `created_at` is a unix timestamp). If `config('queue.default')`
  is not `database`, return `available: false` and skip the queries.
- Failed jobs: read through `Queue::getFailer()` (the `DatabaseFailedJobProvider`)
  / a paginated query on `failed_jobs`; parse the `exception` longtext for a
  one-line summary (first line) and expose the full text on expand.
- Actions: call the same code paths as the Artisan commands —
  `Queue::getFailer()` + `app('queue.failer')`, or dispatch
  `Illuminate\Queue\Console\RetryCommand` semantics via
  `$failer->find()` → re-push → `$failer->forget()`. `retry all` iterates ids.
  `forget` is `$failer->forget($id)`. Unknown uuid → not-found result, no-op.
- Each action calls `AuditLogger::record()` with a new `AuditAction` case
  (`QUEUE_JOB_RETRIED`, `QUEUE_JOB_FORGOTTEN`) — consistent with §83's model of
  auditing sensitive operator actions. These are enum additions only, no new
  permission.

- *Alternative — shell out to `php artisan queue:retry`:* process spawn per
  click, output parsing, worse error handling.

### 4a. AuditAction enum additions

`SHIFT_CHANGED` etc. are already present-but-unwired per §113; add
`QUEUE_JOB_RETRIED` and `QUEUE_JOB_FORGOTTEN` the same way and wire them here.

### 5. Console delivery: Inertia pages with data as props, actions as POSTs

Five `App\Http\Controllers\System\*Controller` classes (Overview, Log, Queue,
Schedule, Audit), each returning `Inertia::render('system/<page>', [...])`.
List/filter state travels in the query string; controllers read it, call the
read service, and pass a page of results + the filter echo. Deferred props
(`Inertia::defer`) for the heavier sections (log scan, top-errors) so the shell
paints immediately with a skeleton, per the project's Inertia guidance.

Retry/forget are `POST /system/queue/failed/{uuid}/retry|forget` and
`POST /system/queue/failed/retry-all`, returning a redirect back with a flash
message.

Frontend: a `system/` layout (sidebar with the five entries, reusing
`nav-main`/`app-sidebar` primitives), one page component per section, status-tile
and log-entry and job-row components local to `resources/js/pages/system/`.
Wayfinder helpers regenerated for the new named routes; the console imports from
`@/routes` / `@/actions`.

### 6. Route/name layout

All inside the re-gated group, names under a `system.` prefix:

```
GET  /system                      system.dashboard   (Overview)
GET  /system/logs                 system.logs
GET  /system/queue                system.queue
POST /system/queue/failed/{uuid}/retry     system.queue.failed.retry
POST /system/queue/failed/{uuid}/forget    system.queue.failed.forget
POST /system/queue/failed/retry-all        system.queue.failed.retry-all
GET  /system/schedule            system.schedule
GET  /system/schedule/{command}  system.schedule.show   (run history)
GET  /system/audit               system.audit
```

## Risks / Trade-offs

- **Log reader performance on a very large single file** → chunked reverse read
  with early termination; a hard cap on bytes scanned per request (config,
  e.g. 50 MB) after which it reports "results truncated, narrow the range".
- **Log-format assumptions** (custom Monolog formatter, multiline JSON logs)
  break parsing → unparseable lines surface as `raw` entries rather than being
  dropped; the viewer stays usable, just less structured.
- **`ScheduledTaskStarting` without a matching finish** (process killed
  mid-run) leaves a `running` row → the prune job and the page treat a
  `running` row older than a threshold as `unknown`/stale.
- **Double-counting or missing runs if the event subscriber is misregistered**
  → covered by a feature test that fakes a scheduled command and asserts exactly
  one row per outcome.
- **Retrying a failed job whose class no longer exists** → the re-push throws;
  the action catches it and reports failure, leaving the `failed_jobs` row in
  place.
- **Widening `system.health.view`'s meaning** from "poll health" to "enter the
  console" → only System Admin holds it today (§113), so no one gains access
  unexpectedly; called out in the proposal as internally breaking.
- **CSRF on actions** → they are in `routes/web.php`, already covered by the web
  middleware group; a test posts without a token and expects 419.

## Migration Plan

1. Ship the migration for `scheduled_task_runs` (additive, no backfill).
2. Deploy; the event subscriber starts recording on the next scheduler tick.
   Schedule page shows "no runs yet" for up to a day for the daily commands —
   acceptable.
3. Re-gate `/system`. Before deploy, confirm the operators who need the console
   hold `system.health.view` (System Admin role). If a broader group needs it,
   that is a role-assignment change, not a code change.
4. Rollback: revert the route-group middleware and the new routes; the new table
   can be left in place (harmless) or dropped. No data migration to reverse.

## Open Questions

- Should the Schedule retention window (30 days) and the log byte-scan cap be
  environment-configurable from the Settings console later, or stay as config
  constants? Deferrable — does not affect specs or task breakdown.
- Whether to add a bounded auto-poll (e.g. every 15s) to the Overview page or
  leave refresh fully manual. Cosmetic; can be decided during frontend work.
