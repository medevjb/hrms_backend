## Purpose

The `/system` console is the session-authenticated operations surface for a
System Admin / DevOps engineer: the one place to confirm the application is
healthy and to review who changed what. It never hosts an HR-facing feature.

## ADDED Requirements

### Requirement: Console access is gated by the system-health permission

Every page and data endpoint under `/system` SHALL require an authenticated web
session whose user holds the `system.health.view` permission. A request without a
session SHALL be redirected to the login screen; an authenticated request from a
user lacking the permission SHALL be rejected with HTTP 403 and SHALL NOT reveal
any console data.

#### Scenario: Unauthenticated visitor

- **WHEN** a visitor with no session requests any `/system` URL
- **THEN** the response redirects to the login screen

#### Scenario: Authenticated user without the permission

- **WHEN** a signed-in user who does not hold `system.health.view` requests any `/system` page or data endpoint
- **THEN** the response is HTTP 403
- **AND** no health, log, queue, schedule, or audit data is included in the response

#### Scenario: Authorized DevOps user

- **WHEN** a signed-in user who holds `system.health.view` opens `/system`
- **THEN** the console overview page renders

### Requirement: Console navigation shell

The console SHALL present a persistent navigation with entries for Overview,
Logs, Queue, Schedule, and Audit. The active entry SHALL be indicated. Each
entry SHALL be reachable by a stable, bookmarkable URL.

#### Scenario: Navigating between console sections

- **WHEN** an authorized user selects a navigation entry
- **THEN** the corresponding section loads at its own URL
- **AND** that entry is shown as active

### Requirement: Health overview dashboard

The Overview page SHALL display the current system health snapshot as labelled
status indicators: application version, environment name, Laravel version, PHP
version, database connectivity with measured latency, local-storage
read/write/delete probe result, scheduler-heartbeat freshness, database-queue
pending count, failed-job count, and the count of error-or-higher log entries in
the last 24 hours. Each indicator SHALL show an ok / degraded / failing state and
the timestamp the snapshot was taken.

#### Scenario: All subsystems healthy

- **WHEN** an authorized user opens the Overview page and every probe succeeds
- **THEN** each indicator shows an ok state with its value
- **AND** the snapshot timestamp is shown

#### Scenario: A subsystem is failing

- **WHEN** the database probe fails at the time the Overview page is loaded
- **THEN** the database indicator shows a failing state with the error message
- **AND** the other indicators still render their own states

#### Scenario: Existing health endpoint is unaffected

- **WHEN** a client calls `GET /api/v1/system/health` with the `system.health.view` permission
- **THEN** it receives the same snapshot fields it received before this change, plus the 24h error count

### Requirement: Audit log viewer

The Audit page SHALL list audit-log entries newest-first with the actor, action,
affected entity, timestamp, originating IP, and recorded detail. It SHALL support
filtering by action, entity type, actor, and a date range, and SHALL paginate
results. The page SHALL be strictly read-only — it SHALL offer no way to create,
edit, or delete an audit entry.

#### Scenario: Browsing audit entries

- **WHEN** an authorized user opens the Audit page
- **THEN** the most recent audit entries are listed with actor, action, entity, timestamp, and IP
- **AND** older entries are reachable through pagination

#### Scenario: Filtering the audit log

- **WHEN** the user filters by an action and a date range
- **THEN** only entries matching that action within that range are listed

#### Scenario: No write path

- **WHEN** the Audit page is rendered
- **THEN** it exposes no control that creates, edits, or deletes an audit entry
