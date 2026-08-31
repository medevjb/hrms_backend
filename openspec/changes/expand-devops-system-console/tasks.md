## 1. Access gate

- [x] 1.1 Add `can:system.health.view` to the `/system` route group in `routes/web.php`, removing the `TODO(Phase 1)` placeholder comment; verify with a feature test that a user without the permission gets 403, an unauthenticated request redirects to login, and a System Admin gets 200.
- [x] 1.2 Add a `config/system-console.php` config file with `schedule.retention_days` (default 30), `logs.max_scan_bytes` (default 50 MB), and `logs.channel_path` (default `storage_path('logs/laravel.log')`); verify `php artisan config:show system-console` prints the keys.

## 2. Scheduled-task run tracking

- [x] 2.1 Create the `scheduled_task_runs` migration (columns per design.md §2) with an index on `command` and `created_at`; verify `php artisan migrate` runs and `database-schema` shows the table.
- [x] 2.2 Add the `ScheduledTaskRun` model with a `status` cast and a `scopeForCommand`; add a factory with `succeeded`/`failed`/`skipped`/`running` states; verify a factory test persists each state.
- [x] 2.3 Add `ScheduledTaskRunSubscriber` listening on `ScheduledTaskStarting`, `ScheduledTaskFinished`, `ScheduledTaskFailed`, `ScheduledTaskSkipped`; open a `running` row on start and close it with duration, exit code, status, and an 8 KB output tail; register it in a service provider. Verify with a test that fakes a scheduled command per outcome and asserts exactly one row with the right status.
- [x] 2.4 Add a `system:prune-schedule-runs` command that deletes runs older than `schedule.retention_days` and marks `running` rows older than 6 hours as `unknown`; schedule it daily in `routes/console.php`. Verify a test with an old run and a stale running row.

## 3. Application log reader

- [x] 3.1 Add `App\Services\LogReader` that reverse-chunk reads the configured log file, splits on the Monolog prefix, attaches trailing lines as the stack trace, and yields entries newest-first. Verify unit tests: a structured ERROR+trace entry, a multi-line message, an unparseable line surfaced as `raw`, and a missing file returning empty.
- [x] 3.2 Add filtering to `LogReader`: minimum level, time range, and substring search over message+trace, applied during the scan with early termination and a `max_scan_bytes` cap that flags truncation. Verify tests for each filter, combined filter+search, and the truncation flag.
- [x] 3.3 Add `LogReader::errorCountSince(CarbonInterface)` and `LogReader::topErrors(CarbonInterface)` (group by masked message — UUIDs/ints/quoted paths → placeholders — with count, max level, last-seen, ordered by count desc). Verify tests for the 24h count and for grouping a recurring error into one row.
- [x] 3.4 Add pagination to the reader's query result object (page, per-page, has-more). Verify a test that a second page returns older matches.

## 4. Health snapshot extension

- [x] 4.1 Append `errors_24h` to `SystemHealthService::snapshot()` sourced from `LogReader::errorCountSince(now()->subDay())`; verify `SystemHealthControllerTest` still passes and add an assertion that the new key is present with the existing keys unchanged.

## 5. Queue monitoring

- [x] 5.1 Add `App\Services\QueueInspector` with `depth()` (total + per-queue counts + oldest-pending age from `jobs`, or `available: false` when `queue.default` is not `database`) and `failedJobs($page)` (paginated `failed_jobs` with a one-line exception summary + full exception). Verify tests with seeded `jobs`/`failed_jobs` rows and with a non-database connection.
- [x] 5.2 Add `QueueInspector::retry($uuid)`, `retryAll()`, `forget($uuid)` using `app('queue.failer')`; unknown uuid returns a not-found result and changes nothing; a re-push failure is caught and reported while the row stays. Verify tests for retry-one (row gone, job re-queued), retry-all (list empty), forget (row gone, not re-queued), and unknown uuid.
- [x] 5.3 Add `QUEUE_JOB_RETRIED` and `QUEUE_JOB_FORGOTTEN` cases to the `AuditAction` enum and call `AuditLogger::record()` from the retry/forget paths with the actor and job uuid. Verify a test asserting an audit row is written for a forget.

## 6. Console controllers and routes

- [x] 6.1 Add `App\Http\Controllers\System\OverviewController` returning `Inertia::render('system/overview', ...)` with the health snapshot (deferred prop for `top_errors`); route `GET /system` → `system.dashboard`. Verify a feature test asserting the Inertia component and the snapshot prop.
- [x] 6.2 Add `System\LogController` — `GET /system/logs` (`system.logs`), reads query filters, passes a deferred page of entries + `top_errors` + filter echo. Verify a feature test with a seeded log file asserting filtered entries in the props.
- [x] 6.3 Add `System\QueueController` — `GET /system/queue` (`system.queue`) with depth + failed-jobs props; `POST /system/queue/failed/{uuid}/retry`, `.../forget`, `POST /system/queue/failed/retry-all` returning redirect-back with a flash. Verify feature tests for the page and each action, including a 419 when posting without a CSRF token.
- [x] 6.4 Add `System\ScheduleController` — `GET /system/schedule` (`system.schedule`) listing scheduler events (cron expression + next due) left-joined with each command's latest run; `GET /system/schedule/{command}` (`system.schedule.show`) with paginated run history. Verify feature tests for a command with runs, a command with none, and the history view.
- [x] 6.5 Add `System\AuditController` — `GET /system/audit` (`system.audit`) reusing the audit query (action/entity/actor/date filters, pagination) as Inertia props, read-only. Verify a feature test for listing and for a filtered result.
- [x] 6.6 Regenerate Wayfinder route helpers (`php artisan wayfinder:generate`) and verify the frontend typecheck passes with the new `system.*` routes.

## 7. Console frontend

- [x] 7.1 Add a `resources/js/layouts/system/` layout: sidebar with Overview / Logs / Queue / Schedule / Audit entries (reusing `nav-main` / `app-sidebar` primitives), active-state indication, breadcrumbs. Verify `npm run build` succeeds and the nav renders on each route.
- [x] 7.2 Build `system/overview.tsx`: status tiles for every snapshot field with ok/degraded/failing state and the snapshot timestamp; skeleton while `top_errors` defers. Verify against the running app that a failing DB probe shows the DB tile red and others still render.
- [x] 7.3 Build `system/logs.tsx`: level + time-range + search controls bound to the query string, paginated entry list, collapsed rows expanding to the full stack trace, and the top-errors summary panel. Verify filtering and trace expansion in the running app.
- [x] 7.4 Build `system/queue.tsx`: depth summary (total, per-queue, oldest age) or the "unavailable for this connection" notice, paginated failed-jobs list with expandable exception, and retry-one / retry-all / forget controls with success/failure feedback. Verify each action round-trips in the running app.
- [x] 7.5 Build `system/schedule.tsx`: table of commands with cron expression, next due, latest run (status/time/duration), recent-failure count; row selection opens the run history with output tails. Verify in the running app after a scheduler tick.
- [x] 7.6 Build `system/audit.tsx`: filter bar (action, entity type, actor, date range), paginated read-only table (actor, action, entity, timestamp, IP, detail), no write controls. Verify filtering in the running app.

## 8. Full-suite verification

- [x] 8.1 Run `vendor/bin/pint --dirty --format agent` and `vendor/bin/phpstan` (if configured) clean.
- [x] 8.2 Run the new and touched feature tests green (`php artisan test --compact` for `System*` and `SystemHealthControllerTest`), then ask the user to run the complete backend suite and the frontend `typecheck` / `lint` / `build`.
- [ ] 8.3 Update `docs/PRD.md` §79 / §113 and `docs/api.md` to describe the console pages — only if the user asks for the docs update. _(not requested — skipped)_
