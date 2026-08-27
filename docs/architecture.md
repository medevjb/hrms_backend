# Architecture

Quick-reference companion to `docs/PRD.md`. If anything here and the PRD disagree, the
PRD wins — this file is a summary, not a second source of truth.

## Repositories

Two independent git repositories, not a monorepo (PRD §3):

```text
hrms_backend    Laravel 13 · PHP 8.4 · MySQL 9 · github.com/medevjb/hrms_backend
hrms_frontend   Next.js 16 · React 19 · TypeScript · github.com/medevjb/hrms_frontend
```

Both keep a copy of `docs/PRD.md` and this directory under their own `docs/`, since
neither repo can see the other's filesystem.

## Request flow

```text
Browser
  │
  ▼
Next.js (hrms_frontend)
  │  Authorization: Bearer <sanctum-token>
  ▼
Laravel /api/v1/*  (hrms_backend)
  │
  ▼
MySQL
```

Laravel is the only place business rules are decided — permissions, attendance status,
lateness, overtime eligibility, leave eligibility, salary deductions, payroll, approval
authority (PRD §4). The frontend renders what the API returns and never re-derives these
decisions client-side.

A second, much smaller surface exists in `hrms_backend`: an Inertia + React console at
`/system`, session-authenticated via Fortify, for the System Admin/DevOps dashboard only
(PRD §5.1, §79). No HR feature is ever built there.

## Backend layering (`hrms_backend/app/`)

```text
Http/Controllers/Api/V1/   JSON controllers Next.js talks to
Http/Controllers/System/   Inertia controllers for the /system console
Http/Requests/             Form Request validation
Http/Resources/            JSON API resources — the response shape
Models/                    Eloquent models
Services/                  business logic (AttendanceService, PayrollService, ...)
Policies/                  Laravel Policies — every authorization decision
Enums/                     every status list in the PRD, as a backed PHP enum
Jobs/                      queued work (payslip PDFs, bulk emails)
Console/Commands/          scheduled jobs (nightly attendance close, holiday reminder)
Notifications/             IN_APP + EMAIL notifications
Support/                   cross-cutting helpers (ApiResponse, money helpers)
```

Controllers stay thin. Business rules live in `Services/`, authorization in `Policies/`,
using `ScopeResolver` to turn `(user, permission, scope)` into an employee-ID set (PRD §10).

## Frontend layering (`hrms_frontend/`)

```text
app/            Next.js App Router — route groups, layouts, pages
features/       one folder per domain (employees, attendance, leave, payroll, ...) —
                owns its components, hooks, and TanStack Query keys
components/     ui/, forms/, tables/, charts/, layouts/ — shared, cross-feature
hooks/          shared hooks
lib/            api-client.ts, theme.ts, cross-cutting utilities
services/       one module per API resource group
types/          mirrors backend enums and API resources
e2e/            Playwright specs
```

Mantine owns components, theming, and dark mode. Tailwind is layout utilities only
(grid/flex/spacing) — its Preflight reset is disabled so it never fights Mantine's own
reset (PRD §6.2). Cross-feature imports go through `components/` or `lib/`, never
feature-to-feature.

## Auth model

```text
Next.js (all HR features)   Sanctum Bearer personal access tokens
/system console only        Fortify session cookies
```

Fortify supplies the actual login/2FA/password-reset logic; `/api/v1/auth/*` exposes it
as JSON and issues a Sanctum token on success (PRD §92). Self-registration is closed —
employees enter through an HR-initiated invite, not `/register` (PRD §92.4, §148 #2).

## Non-negotiables (PRD §124–§125, §141)

* no hard-coded role IDs, leave quantities, payroll cutoff, overtime rate, or grace
  minutes — these are configuration, resolved through a service at evaluation time;
* money is `DECIMAL(15,4)` in MySQL and BCMath in PHP, never float;
* a FINALIZED/PAID/LOCKED payroll period is never silently recalculated — late changes
  become an arrear on the next period (PRD §72, §146);
* attendance and payroll records snapshot the rule values used, so history stays
  reproducible after settings change (PRD §22, §95).

## What's deliberately not here yet

Redis, S3, Horizon, Pulse, microservices, an event bus — none of these are V1 (PRD §1,
§130). Local database queue and local private storage cover V1's needs.
