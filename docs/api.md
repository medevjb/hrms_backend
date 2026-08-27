# API

Quick-reference companion to `docs/PRD.md` §86–§91, §139. If anything here and the PRD
disagree, the PRD wins.

## Base

```text
https://<host>/api/v1
Authorization: Bearer <sanctum-token>
Accept: application/json
```

## Envelope (PRD §139.1)

```json
// success
{ "data": { }, "meta": { } }

// collection
{ "data": [ ], "meta": { "current_page": 1, "per_page": 25, "total": 137, "last_page": 6 } }
```

## Errors (PRD §139.2)

```json
{
  "message": "The given data was invalid.",
  "errors": { "field": ["reason"] },
  "code": "VALIDATION_FAILED"
}
```

```text
200 ok                401 unauthenticated (token missing/expired/revoked)
201 created           403 unauthorized (policy denied)
202 accepted           404 not found — also "out of your scope" (never 403 for that)
204 no content          409 conflict (duplicate check-in, already finalized)
422 validation failed    429 throttled
```

`code` is the stable machine-readable field — switch on it, never on `message` text.

## Listing conventions (PRD §139.3)

```text
?page=1&per_page=25          per_page max 100
?sort=-created_at,last_name  leading "-" = descending
?search=nabil
?filter[status]=LATE&filter[team_id]=4&filter[date_from]=...&filter[date_to]=...
?include=employee,shift      allow-listed per endpoint — never pass raw input to a query builder
```

## Dates and money (PRD §139.4)

```text
Timestamps   ISO-8601 with offset   "2026-08-20T09:07:00+06:00"
Dates        "2026-08-20"
Times        "09:00"                24-hour, no seconds
Durations    integer minutes
Money        decimal string         "30000.0000"   never a JSON number
Enums        SCREAMING_SNAKE_CASE, matching the backend PHP enum exactly
```

## Idempotency (PRD §139.5)

At most one open check-in per `(employee, work_date)` — enforced by a unique DB index,
not just an application check. A second check-in returns 409 `ALREADY_CHECKED_IN` with
the existing record in `data`, so the frontend can render correct state from the error
alone. Approval endpoints (`/approve`, `/reject`, `/finalize`) are idempotent by state —
re-approving an already-approved record is 409, never a duplicate row.

## Endpoint groups (PRD §139.6 — build each as its phase lands)

```text
auth          /auth/login  /auth/logout  /auth/two-factor-challenge
              /auth/forgot-password  /auth/reset-password  /auth/accept-invitation
              /auth/me  /auth/password  /auth/sessions  /auth/sessions/{id}

employees     /employees  /employees/{id}  /employees/{id}/status
              /employees/{id}/salary (financial-permission gated)
              /employees/{id}/documents  /employees/{id}/transfer

org           /departments  /teams  /teams/{id}/members

shifts        /shifts  /shift-overrides

attendance    /attendance/today  /attendance/check-in  /attendance/check-out
              /attendance  /attendance/{id}/adjust

leave         /leave-types  /leave-requests  /leave-requests/{id}/approve|reject|cancel
              /leave-balances  /leave-balances/{id}/adjust

overtime      /overtime  /overtime/{id}  /overtime/{id}/approve|reject  /overtime/{id}/adjust

holidays      /holidays  /holiday-notices  /holiday-notices/{id}/approve|download

announcements /announcements  /announcements/{id}/publish|read

payroll       /payroll/settings  /payroll/periods  /payroll/runs  /payroll/entries
              /payroll/entries/{id}/adjust|release|acknowledge|dispute|payslip
              /payroll/disputes  /payroll/disputes/{id}/resolve  /payroll/arrears

settings      /settings/organization|payroll|overtime|leave-policies|late-penalty-rules

dashboard     /dashboard   (role-aware payload)

reports       /reports/{type}  /reports/{type}/export  /reports/exports/{id}

notifications /notifications  /notifications/{id}/read  /notifications/read-all

files         /documents/{id}/download   (authorized private stream, never a public URL)

audit         /audit-logs

system        /system/health   (system.health.view — /system console, not this frontend)
```

## Versioning (PRD §139.7)

Within `v1`, only additive changes: new endpoints, new optional request fields, new
response fields. Removing/renaming a field, changing a type, or adding a required
request field is a `v2` concern — don't do it inside `v1`.
