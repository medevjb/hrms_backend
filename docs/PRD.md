# Agency HRM System

## Product Requirements Document — Simplified MVP v4.0

**Product:** Agency Human Resource Management System
**Architecture:** API-first modular monolith
**Backend:** Laravel 13 (PHP 8.4) + MySQL 9.7 (DBngin)
**API Auth:** Laravel Sanctum — Bearer personal access tokens
**Frontend:** Next.js 16 + React 19 + TypeScript 5
**UI System:** Mantine (components) + Tailwind CSS v4 (layout utilities)
**Backend Console:** Inertia + React — retained for the System Admin/DevOps dashboard only
**Development Strategy:** Strict linear phase-by-phase implementation using AI coding agents
**Infrastructure Strategy:** Simple first, scalable later

**Document Status:** Validated against the actual `backend/` and `frontend/` scaffolds on
**2026-08-27**. Every technology claim below reflects what is installed on disk or what
Phase 0 must install. See **§134 Scaffold Validation Report** for the full reconciliation
between v3.0 and the code, and **§135 Phase 0 Prerequisites** for what is not yet provisioned.

---

# 1. Product Objective

Build a complete but technically simple HRM for an agency-based company.

The system will manage:

* employees;
* departments;
* teams;
* organizational hierarchy;
* roles and permissions;
* shifts;
* flexible late/grace time;
* attendance;
* attendance corrections;
* leave;
* overtime;
* holidays;
* holiday notices;
* announcements;
* salary;
* payroll;
* attendance penalties;
* overtime payments;
* payslips;
* employee salary acknowledgement;
* dashboards;
* reports;
* audit logs;
* basic application health.

The first version should avoid unnecessary infrastructure.

Initial technical ecosystem:

```text
Laravel
MySQL
Next.js
React
TypeScript
Mantine
Local Private Storage
SMTP
Cron
Laravel Database Queue when required
```

The following are explicitly **not required for V1**:

```text
Redis
S3
Laravel Horizon
Laravel Pulse
Kafka
RabbitMQ
Elasticsearch
Kubernetes
Microservices
External Event Bus
Complex distributed infrastructure
```

These can be introduced later without redesigning the HR business domains.

---

# 2. Product Philosophy

The system should follow this priority:

```text
Correct HR Business Rules
        ↓
Security
        ↓
Data Integrity
        ↓
Auditability
        ↓
Maintainability
        ↓
User Experience
        ↓
Performance
        ↓
Infrastructure Scaling
```

The goal is not to build for hypothetical millions of users.

The goal is to create a clean and stable HRM that can grow naturally.

---

# 3. Project Structure

The complete product lives in one parent directory with two separate applications.

**Actual structure on disk (verified 2026-08-27):**

```text
hrms/                          ← repository root (NOT "agency-hrm")
│
├── backend/                   ← Laravel 13 · laravel/react-starter-kit base
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/js/          ← Inertia React — see §5.1
│   ├── routes/
│   └── tests/
│
├── frontend/                  ← Next.js 16 · create-next-app base
│   ├── app/
│   ├── public/
│   └── AGENTS.md              ← Next.js 16 agent rules — see §6.1
│
└── docs/
    └── PRD.md                 ← this document
```

**Structure gaps Phase 0 must close:**

```text
docs/architecture.md     MISSING — referenced by §127
docs/database.md         MISSING — referenced by §127
docs/permissions.md      MISSING — referenced by §127
docs/api.md              MISSING — referenced by §127
README.md                MISSING at repository root
backend/routes/api.php   MISSING — see §5.2
```

§127 instructs every coding agent to read four documents that do not exist. Until Phase 0
authors them, §127's reading list is **PRD.md only**, and agents must not block on the others.

**Version control state:**

```text
hrms/            not a git repository — docs/ is currently ungoverned by version control
hrms/backend/    its own git repository — origin: github.com/medevjb/hrms_backend
hrms/frontend/   its own git repository — origin: github.com/medevjb/hrms_frontend
```

This is the deliberate strategy, not a gap: backend and frontend are two independently
installable, testable, deployable applications (per this section's own requirement below),
so they version separately. Each phase's backend work and frontend work are committed to
their own repository, with their own history — do not try to unify them into one repo, and
do not treat one as a submodule of the other.

`docs/PRD.md` itself has no home in either repository, since it sits one level above both
at `hrms/`. Phase 0 should copy or symlink it into both repos (e.g. `backend/docs/PRD.md`
and `frontend/docs/PRD.md`) so it travels with clones of either one — an agent that only
checks out `hrms_backend` must still be able to read the PRD.

The two applications must remain independently:

* installable;
* testable;
* buildable;
* deployable.

---

# 4. System Architecture

```text
                       USERS
                         │
                         ▼
              ┌────────────────────┐
              │      Next.js       │
              │                    │
              │ React              │
              │ TypeScript         │
              │ Mantine            │
              └──────────┬─────────┘
                         │
                    HTTPS REST API
                         │
                         ▼
              ┌────────────────────┐
              │      Laravel       │
              │                    │
              │ Authentication     │
              │ Authorization      │
              │ Employees          │
              │ Shifts             │
              │ Attendance         │
              │ Leave              │
              │ Overtime           │
              │ Payroll            │
              │ Notifications      │
              └──────────┬─────────┘
                         │
                         ▼
                 ┌──────────────┐
                 │    MySQL     │
                 └──────────────┘

Additional V1 services:

Local Private Storage
SMTP
Cron
Database Queue when necessary
```

**Transport contract:**

```text
Next.js  →  Authorization: Bearer <sanctum-token>  →  Laravel /api/v1/*
                     responses are JSON only
```

Laravel additionally serves one Inertia-rendered page tree at `/system/*` for the
System Admin/DevOps dashboard (§79). That console is session-authenticated, is not part
of the public API surface, and no HR-facing feature may be built there.

Laravel is the authoritative business system.

The frontend must not make final decisions about:

* permissions;
* attendance status;
* lateness;
* overtime eligibility;
* leave eligibility;
* leave balance;
* salary deductions;
* payroll;
* approval authority.

---

# 5. Backend Technology

## 5.0 Installed vs. Required

**Already installed** (`backend/composer.json`, verified):

```text
laravel/framework    ^13.17     PHP ^8.3 (runtime is 8.4.23)
laravel/fortify      ^1.37.2    auth flows — login, register, reset, verify, 2FA
laravel/passkeys                transitive dep of Fortify 1.39+; migration unpublished — §92.5
inertiajs/inertia-laravel ^3.0  retained for §79 console only
laravel/wayfinder    ^0.1.14    typed route helpers for the Inertia console
laravel/chisel       ^0.1.0
laravel/tinker       ^3.0
pestphp/pest         ^5.1       test framework — function-style tests, not PHPUnit classes
pestphp/pest-plugin-laravel ^5.0
laravel/boost        ^2.2       AI-agent MCP server — see 5.0.1
larastan/larastan    ^3.9       static analysis
laravel/pint         ^1.27      formatting
```

**Phase 0 must install:**

```text
laravel/sanctum          API token authentication — NOT PRESENT
doctrine/dbal            only if column-change migrations require it
barryvdh/laravel-dompdf  payslip + holiday notice PDF generation (§56, §71)
```

v3.0 said "Pest or PHPUnit". Pest 5 is installed and the 13 existing feature tests are
already written in Pest's function style (`test('...', function () {...})`). **V1
standardises on Pest.** Create tests with `php artisan make:test --pest {Name}`, run them
with `php artisan test` or `vendor/bin/pest`.

### 5.0.1 Laravel Boost

`laravel/boost` ships an MCP server plus `backend/.claude/skills/*` and the guidance baked
into `backend/CLAUDE.md`. Any agent working inside `backend/` must follow it:

* activate the matching skill in `.claude/skills/` before working in that domain
  (Fortify, Inertia+React, Wayfinder, Tailwind, testing);
* check `.ai/rules/index.md` for area-grouped rules before creating or editing a file —
  it does not exist yet; Phase 0 does not need to create it, but a later phase that
  accumulates non-obvious decisions should use `record-rule` rather than only writing them
  here;
* run `vendor/bin/pint --dirty --format agent` after any PHP change;
* prefer `php artisan make:*` generators over hand-written boilerplate;
* use named routes with Wayfinder-generated TypeScript for the `/system` console only —
  Next.js talks to the JSON API and has no Wayfinder access, so its API calls go through
  `lib/api-client.ts` (§6.3), not generated route helpers.

This governs *how* backend code gets written; it does not change any business rule in this
PRD.

---

## 5.1 The Inertia Layer

The starter kit ships a complete React/Inertia SPA in `backend/resources/js`:

```text
resources/js/pages/
├── welcome.tsx
├── dashboard.tsx
├── auth/          login, register, forgot-password, reset-password,
│                  verify-email, confirm-password, two-factor-challenge
└── settings/      profile, security, appearance
```

**Decision:** the Inertia layer is retained **only** for the §79 System Admin/DevOps
dashboard. Phase 0 must:

```text
KEEP    Inertia, Vite, Wayfinder, resources/js/components/ui, layouts
KEEP    HandleInertiaRequests + HandleAppearance middleware
MOVE    dashboard.tsx  →  the /system technical console (§79)
DELETE  resources/js/pages/settings/*   — Next.js owns employee settings
DELETE  resources/js/pages/welcome.tsx
CONVERT resources/js/pages/auth/*       — see §92; Fortify must answer JSON
        for /api/v1/auth/*, while the /system console keeps session login
GATE    every /system route behind the system.health.view permission (§11)
```

No HR-facing feature — employees, attendance, leave, overtime, payroll, holidays,
announcements, reports — may ever be built as an Inertia page. Those belong to Next.js.

---

## 5.2 API Routing

`bootstrap/app.php` currently registers **web and console routes only**:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

There is no `routes/api.php` and no `api:` entry. Phase 0 must add both:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api/v1',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

The existing `withExceptions` block already forces JSON for `api/*` — keep it.

`/up` is Laravel's built-in health endpoint and satisfies the §101 "basic health endpoint"
requirement. §79's richer diagnostics are a separate authenticated endpoint.

---

## 5.3 Layering Rules

Keep controllers thin. Business rules live in service/action classes.

**Current `app/` (verified):**

```text
app/
├── Actions/Fortify/    CreateNewUser, ResetUserPassword
├── Concerns/           PasswordValidationRules, ProfileValidationRules
├── Console/Commands/   InstallFeaturesCommand
├── Http/
│   ├── Controllers/    Controller, Settings/ProfileController, Settings/SecurityController
│   ├── Middleware/     HandleInertiaRequests, HandleAppearance
│   └── Requests/       Settings/*
├── Models/             User  ← the ONLY model that exists
└── Providers/          AppServiceProvider, FortifyServiceProvider
```

**Target `app/`:**

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/          all JSON controllers for Next.js
│   │   └── System/          Inertia controllers for the §79 console
│   ├── Requests/
│   └── Resources/           JSON API resources — see §139
│
├── Models/
│
├── Services/
│   ├── AttendanceService.php
│   ├── ShiftService.php          resolves shift + grace for (employee, date) — §125
│   ├── LeaveService.php
│   ├── OvertimeService.php
│   ├── PayrollService.php
│   ├── HolidayService.php
│   └── ScopeResolver.php         resolves permission scope → employee id set — §10
│
├── Policies/
├── Jobs/
├── Console/Commands/             nightly attendance close, holiday reminder — §137
├── Notifications/
├── Enums/                        every status list in this PRD is a backed PHP enum
└── Support/Money.php             decimal arithmetic helper — §141
```

Every status set defined in this document (§13, §29, §39, §51, §64, §70) must be a backed
PHP enum, not a loose string, and must be mirrored by a TypeScript union in
`frontend/types/`.

Avoid unnecessary architectural abstractions.

---

# 6. Frontend Technology

## 6.0 Installed vs. Required

**Already installed** (`frontend/package.json`, verified — 3 runtime dependencies total):

```text
next          16.3.3
react         19.2.8
react-dom     19.2.8
tailwindcss   ^4         + @tailwindcss/postcss
typescript    ^5         eslint ^9, eslint-config-next
```

**Phase 0 must install:**

```text
@mantine/core @mantine/hooks @mantine/form @mantine/dates
@mantine/notifications @mantine/charts @mantine/modals @mantine/dropzone
@tabler/icons-react
dayjs                          peer dependency of @mantine/dates
recharts                       peer dependency of @mantine/charts
@tanstack/react-query @tanstack/react-query-devtools
zod
postcss-preset-mantine postcss-simple-vars
@playwright/test               dev
```

Neither Mantine nor TanStack Query nor Zod nor Playwright is currently present. Every
v3.0 statement about Mantine described an intention, not a fact.

---

## 6.1 Next.js 16 Constraint

`frontend/AGENTS.md` — generated by `next dev` — carries a standing instruction:

> This version has breaking changes — APIs, conventions, and file structure may all differ
> from your training data. Read the relevant guide in `node_modules/next/dist/docs/`
> before writing any code.

This binds every coding agent. Before writing routing, data-fetching, caching, or
middleware code, consult `frontend/node_modules/next/dist/docs/`. Do not assume Next.js
13/14/15 conventions. `AGENTS.md` is rewritten by `next dev`; commit it rather than
fighting the diff.

---

## 6.2 shadcn/ui + Tailwind

**Reversed after Phase 3.** This section originally mandated Mantine, with Tailwind
restricted to page-level layout, and told future agents to drop Tailwind before dropping
Mantine if the boundary proved costly. Phases 0–3 shipped against that rule. The user then
asked for a full visual/UX rebuild on shadcn/ui — a deliberate, explicit reversal of a
documented decision, not scope creep — and every Phase 0–3 screen was rebuilt accordingly
in the same pass, so there is no mixed-library debt left behind. Any UI requirement
elsewhere in this document that names a Mantine component (§7, §100) means "the shadcn/ui
equivalent" from here on.

Current stack:

```text
tailwindcss v4          utility layer — Preflight enabled, no coexistence exclusion
shadcn/ui (radix-nova)  component source lives in components/ui/*, generated via the
                        shadcn CLI and then hand-edited like any other app code — it is
                        not a package dependency to upgrade, it's checked-in source
radix-ui                the underlying unstyled primitives shadcn/ui wraps
lucide-react            icons (replaces @tabler/icons-react)
sonner                  toasts (replaces @mantine/notifications)
next-themes             light/dark mode (replaces @mantine/core's ColorSchemeScript)
react-day-picker + date-fns   the date picker shadcn/ui wraps (replaces @mantine/dates)
recharts                unchanged — already the engine behind shadcn's chart component
```

Rules:

* Design tokens live as CSS custom properties in `app/globals.css` (`:root` / `.dark`),
  wired into Tailwind via `@theme inline`. That file is the one source of truth for
  color/radius/spacing — don't hand-roll competing values in component files.
* `components/ui/*` is project source, not a vendored dependency — edit it directly
  rather than fighting it with wrapper components, and don't `npx shadcn add --overwrite`
  a file that's been customized without re-applying the customization.
* The signature status language (`components/ui/status-chip.tsx` — a dot + label pill,
  color-coded by semantic tone) is the one recurring visual motif across employee status,
  shift active/inactive, holiday type, and settings save state. Reach for it before
  inventing a new status representation.
* Forms are plain controlled state (`useState` + a `zod` `safeParse` on submit), not
  react-hook-form — `components/ui/form.tsx` exists from the CLI install but is unused;
  keep it that way unless a form's complexity genuinely outgrows the simple pattern.

---

## 6.3 Organization

**Actual (verified):** `app/layout.tsx`, `app/page.tsx`, `app/globals.css`, `app/favicon.ico`.
Nothing else. No API client, no `.env`, no `.env.example`.

**Target:**

```text
frontend/
├── app/
│   ├── (auth)/               login, forgot-password, reset-password, 2fa
│   ├── (dashboard)/          authenticated shell — sidebar + header
│   └── layout.tsx            MantineProvider + QueryClientProvider
│
├── features/
│   ├── auth/
│   ├── employees/
│   ├── departments/
│   ├── teams/
│   ├── shifts/
│   ├── attendance/
│   ├── leave/
│   ├── overtime/
│   ├── holidays/
│   ├── announcements/
│   ├── payroll/
│   ├── reports/
│   ├── settings/
│   └── dashboard/
│
├── components/
│   ├── ui/
│   ├── forms/
│   ├── tables/
│   ├── charts/
│   └── layouts/
│
├── hooks/
├── lib/
│   ├── api-client.ts         fetch wrapper — attaches Bearer token, unwraps §139 envelope
│   ├── auth.ts               token storage, refresh, logout
│   └── permissions.ts        can(permission) helper — display only, never a security control
├── services/                 one module per API resource group
└── types/                    mirrors backend enums and API resources
```

Each `features/*` folder owns its components, hooks, and TanStack Query keys. Cross-feature
imports go through `components/` or `lib/`, never feature-to-feature.

**Environment:** Phase 0 must create `frontend/.env.example`:

```text
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

`backend/.env` sets `APP_URL=http://localhost:8000`. Keep the two consistent, and add the
frontend origin to the backend CORS allow-list (§93).

---

# 7. UI / UX Direction

The application should look like a professional workforce-management platform.

Use:

* collapsible sidebar;
* responsive dashboard;
* clear page headers;
* searchable/filterable tables;
* status badges;
* cards for KPI summaries;
* date-range controls;
* dialogs for confirmations;
* drawers for quick detail screens;
* charts only where they provide useful information.

Avoid unnecessarily decorative admin templates.

The UI should prioritize:

```text
Clarity
Speed
Data Density
Consistency
Accessibility
```

---

# 8. Roles

Business roles:

1. Admin
2. Head of HR
3. HR
4. Operation Manager
5. Team Leader
6. Team Member

Optional technical role:

7. System Admin / DevOps

---

# 9. Organizational Chain

## Operation Chain

```text
Admin
  ↓
Operation Manager
  ↓
Team Leader
  ↓
Team Member
```

## HR Chain

```text
Admin
  ↓
Head of HR
  ↓
HR
  ↓
Team Leader / Team Member
```

HR authority and operational reporting authority are different concepts.

---

# 10. Authorization Architecture

Authorization should follow:

```text
Role
+
Permission
+
Scope
=
Access
```

Scopes (this is the complete V1 set — do not invent others):

```text
SELF             the acting user's own records only
TEAM             every member of teams the user leads
DEPARTMENT       every team inside departments the user manages
OPERATION        every employee under the user's operational chain (§9)
HR_SCOPE         every employee the user is HR-responsible for
ALL_EMPLOYEES    the entire workforce
SYSTEM           technical/infrastructure resources, no employee data
```

v3.0 omitted `DEPARTMENT`, yet §33 filters attendance by department and §14 puts an
Operation Manager above a department. Without it, an Operation Manager over several teams
had no expressible scope. It is now part of the set.

A single service — `ScopeResolver` (§5.3) — turns `(user, permission, scope)` into a set of
employee IDs. Every query that returns employee-bound data passes through it. Policies and
query scopes must never re-derive the employee set independently, or the two will drift.

Example:

```text
Team Leader

Permission:
attendance.view

Scope:
TEAM
```

means the Team Leader can see attendance only for their team.

---

# 11. Core Permissions

Examples:

```text
employee.view
employee.create
employee.update
employee.archive
employee.financial.view      ← §12 requires stronger permissions on salary data
employee.financial.manage    ← set/change salary and payment information

department.view              ← §14 manages departments; v3.0 had no permission for it
department.manage

team.view
team.manage

shift.view
shift.manage
shift.override

attendance.view
attendance.manage
attendance.correct

leave.request
leave.review
leave.approve
leave.override
leave.policy.manage
leave.balance.adjust         ← §37 balance adjustments are a distinct privilege

overtime.view
overtime.review
overtime.approve
overtime.adjust
overtime.policy.manage

holiday.view
holiday.manage
holiday.notice.approve

announcement.view
announcement.create
announcement.publish

payroll.view
payroll.prepare
payroll.adjust
payroll.finalize
payroll.dispute.resolve      ← §70 disputes need an owner

payslip.view_self
payslip.view_all

report.view                  ← §99 reports had no permission
report.export                ← exporting payroll data is more sensitive than viewing it

document.view
document.manage

settings.manage
payroll.settings.manage
attendance.settings.manage

audit.view

system.health.view
```

**Additions over v3.0 and why:**

| Permission | Gap it closes |
| --- | --- |
| `employee.financial.view` / `.manage` | §12 states financial data needs stronger permissions than profile data, but v3.0 defined no permission expressing that. Without these, any holder of `employee.view` sees salary. |
| `department.view` / `.manage` | §14 gives Admin/HR department CRUD with no permission behind it. |
| `leave.balance.adjust` | §37 allows balance adjustments; separating it from `leave.approve` stops an approver silently minting leave days. |
| `payroll.dispute.resolve` | §70 defines a DISPUTED → RESOLVED transition with no permission gating it. |
| `report.view` / `.export` | §99 and §112 define reports with no authorization. Export is separated because a payroll CSV leaving the system is a higher-consequence act than reading one on screen. |
| `document.view` / `.manage` | §82 and §112 require private file access control. |

Every permission is paired with a scope at assignment time. `attendance.view` alone is
meaningless; `attendance.view @ TEAM` is the real grant.

Do not hard-code authorization by role ID.

---

# 12. Employee Management

Employee records contain:

## Personal Information

* employee ID;
* first name;
* last name;
* profile image;
* email;
* phone;
* address;
* emergency contact.

## Employment

* joining date;
* designation;
* department;
* team;
* Team Leader;
* Operation Manager;
* employment type;
* employment status;
* probation status;
* confirmation date.

## Work Configuration

* office/location;
* timezone;
* default shift;
* overtime eligibility.

## Payroll

* salary;
* salary components;
* currency;
* payment information.

Financial data must have stronger permissions than normal employee profile information.

---

# 13. Employee Status

Support:

```text
INVITED
ACTIVE
PROBATION
NOTICE_PERIOD
SUSPENDED
RESIGNED
TERMINATED
ARCHIVED
```

---

# 14. Departments and Teams

The system should support:

```text
Department
    ↓
Operation Manager
    ↓
Team
    ↓
Team Leader
    ↓
Team Members
```

Admin/HR should be able to:

* create departments;
* create teams;
* assign Operation Managers;
* assign Team Leaders;
* add team members;
* transfer employees;
* remove employees from teams.

Historical team assignments should be retained where practical.

---

# 15. Shift Management

Every normal employee should have a default shift.

Example:

```text
Standard Shift

Start:
09:00 AM

End:
06:00 PM
```

Shift fields:

```text
name
start_time
end_time
expected_work_minutes
break_minutes
active
```

---

# 16. Flexible Late Grace Time

The attendance system must support a configurable **late grace period / flexible check-in time**.

This is a critical attendance rule.

Example:

```text
Shift Start:
09:00 AM

Flexible Late Grace:
10 Minutes
```

Then:

```text
08:59 → On Time
09:00 → On Time
09:05 → On Time
09:09 → On Time
09:10 → On Time
09:11 → Late
```

The employee should only become late when:

```text
Check-In Time
>
Shift Start + Grace Minutes
```

Therefore:

```text
09:00 Shift
+
10 Minutes Grace
=
Late begins after 09:10
```

---

# 17. Grace Time Configuration

Admin must be able to configure the grace period.

Example:

```text
Attendance Settings
→ Late Grace Period
→ 10 Minutes
```

Admin may change this to values such as:

```text
0 Minutes
5 Minutes
10 Minutes
15 Minutes
20 Minutes
30 Minutes
```

The value should be entered as minutes rather than hard-coded options.

Recommended validation:

```text
Minimum:
0 minutes

Maximum:
120 minutes
```

Maximum may be adjusted later.

---

# 18. Global Grace Period

For V1, use a global organization-level default.

Example:

```text
Global Late Grace:
10 Minutes
```

This applies to all shifts unless a shift-specific override exists.

---

# 19. Optional Shift-Specific Grace Period

To remain flexible without adding significant complexity, each shift may optionally override the global setting.

Example:

```text
Organization Default:
10 Minutes

Standard Shift:
Use Global

Night Shift:
20 Minutes
```

Recommended field:

```text
late_grace_minutes = NULL
```

means:

```text
Use Global Setting
```

If:

```text
late_grace_minutes = 20
```

then that shift uses 20 minutes.

---

# 20. Grace Time Calculation Example

Employee:

```text
Shift:
09:00 – 18:00

Grace:
10 minutes

Check-In:
09:09
```

Result:

```text
PRESENT
Late Minutes:
0
```

Employee:

```text
Check-In:
09:11
```

Result:

```text
LATE
```

Actual late minutes should normally be:

```text
09:11 - 09:00
=
11 Late Minutes
```

However, payroll penalty rules may separately decide how those late minutes are treated.

Attendance classification and salary punishment are separate concerns.

---

# 21. Grace Period and Payroll

A grace period protects the employee from being marked late.

Example:

```text
Shift:
09:00

Grace:
10

Check-In:
09:08
```

Result:

```text
Late:
NO

Payroll Late Penalty:
NO
```

For:

```text
Check-In:
09:15
```

attendance result:

```text
Late:
YES

Actual Late:
15 Minutes
```

Whether this causes salary deduction depends on payroll policies.

It must not automatically deduct salary simply because the employee was late.

---

# 22. Grace Period Change History

Changing the grace setting must not retroactively recalculate finalized historical attendance.

Example:

August:

```text
Grace:
10 minutes
```

September:

```text
Admin changes grace:
20 minutes
```

Previously finalized August attendance should remain based on the 10-minute rule.

Recommended approach:

Store the effective grace minutes used with the attendance record.

Example:

```text
attendance_records.grace_minutes_used = 10
```

This makes historical attendance reproducible.

---

# 23. Temporary Shift Change

HR/Admin can change an employee's shift for one particular day.

Example:

```text
Default:
09:00 → 18:00

August 20:
12:00 → 21:00
```

Required:

* employee;
* work date;
* new shift;
* reason;
* changed by;
* timestamp.

This must not permanently change their regular shift.

---

# 24. Attendance Architecture

Attendance should use two concepts.

## Attendance Events

Raw actions such as:

```text
CHECK_IN
CHECK_OUT
```

Fields:

```text
employee_id
event_type
event_time
source
created_by
metadata
```

Sources:

```text
WEB
MANUAL
IOT
IMPORT
```

V1 implements:

```text
WEB
MANUAL
```

`IOT` remains future-ready.

---

# 25. Daily Attendance Record

Each work date should produce a summarized attendance record.

Fields conceptually include:

```text
employee_id
work_date

shift_id
shift_start
shift_end

grace_minutes_used

check_in
check_out

worked_minutes
late_minutes

status

is_manual_adjustment
```

---

# 26. Login Attendance Flow

When an employee logs in:

1. Next.js requests today's attendance state.
2. Laravel determines whether today is a workday.
3. If employee has not checked in, frontend opens a check-in popup.
4. Employee confirms.
5. Laravel creates the check-in event.
6. Laravel determines whether the employee is late.

Example UI:

```text
Good Morning

Today's Shift:
09:00 AM – 06:00 PM

Flexible Check-In Until:
09:10 AM

Current Time:
09:07 AM

[ Check In ]
[ Not Now ]
```

Showing the grace end-time provides useful transparency.

---

# 27. Checkout Flow

After check-in:

```text
Checked In:
09:07 AM

Working:
07h 45m

[ Check Out ]
```

The employee confirms before checkout is saved.

---

# 28. Duplicate Attendance Protection

The system must prevent:

* duplicate check-ins;
* duplicate checkouts.

Requests should behave idempotently.

---

# 29. Attendance Statuses

Support:

```text
PRESENT
LATE
ABSENT
ON_LEAVE
HOLIDAY
WEEKEND
HALF_DAY
MISSING_CHECKOUT
MANUALLY_ADJUSTED
```

`PRESENT` and `LATE` are produced at check-in. **Every other status is produced by the
nightly close job (§137)** — without it, `ABSENT`, `MISSING_CHECKOUT`, `HOLIDAY`, and
`WEEKEND` records are never created and absence cannot be counted or deducted.

`MANUALLY_ADJUSTED` is not a classification — it is the marker set by §32 corrections. The
record keeps its real status (`PRESENT`, `LATE`, …) and carries `is_manual_adjustment = true`
alongside it, so a corrected day still counts correctly in §33 analytics and §66 payroll.
Treating it as a status would erase the day from every report.

`HALF_DAY` production is defined in §138.

---

# 30. Late Calculation

Conceptual backend algorithm:

```text
scheduled_start
=
09:00

grace_minutes
=
10

grace_end
=
09:10
```

If:

```text
check_in <= 09:10
```

then:

```text
is_late = false
```

If:

```text
check_in > 09:10
```

then:

```text
is_late = true
```

---

# 31. Late Minutes

For analytics, store the employee's actual lateness from shift start.

Example:

```text
Shift:
09:00

Check-In:
09:18
```

Then:

```text
Late Minutes:
18
```

Grace period determines whether the employee is considered late, but does not need to erase the raw timing difference.

This allows future policies such as:

```text
Grace:
10 minutes

Check-In:
09:18

Late:
YES

Actual Late:
18 minutes

Chargeable Late:
8 minutes
```

if the organization later wants that calculation model.

For V1, payroll penalty policy can remain simpler.

---

# 32. Manual Attendance Correction

Authorized:

* Admin;
* Head of HR;
* HR with permission.

They can correct:

* check-in;
* checkout;
* attendance status.

Every correction requires:

```text
Old Value
New Value
Reason
Changed By
Changed At
```

Original history must remain auditable.

---

# 33. Attendance Dashboard

Higher roles can view attendance for employees under their authority.

Filters:

* employee;
* date;
* date range;
* department;
* team;
* Team Leader;
* Operation Manager;
* shift;
* attendance status;
* late;
* absent;
* missing checkout.

Analytics:

```text
Present
Late
Absent
Leave
Average Working Hours
Attendance Percentage
```

---

# 34. Leave Management

Leave quantities must be configurable.

Example initial policies:

```text
Casual Leave:
15 days

Sick Leave:
10 days
```

Admin and Head HR control these policies.

Never hard-code these quantities.

---

# 35. Leave Categories

Support:

```text
Casual Leave
Sick Leave
Annual Leave
Unpaid Leave
Maternity Leave
Paternity Leave
Special Leave
```

Additional types may be created.

---

# 36. Leave Policy Configuration

Each type may contain:

* annual allocation;
* paid/unpaid;
* half-day support;
* carry-forward;
* document requirement;
* maximum consecutive days;
* minimum employment period.

---

# 37. Leave Balance

Use:

```text
Opening Balance
+ Added
+ Carried Forward
+ Adjustment
- Approved Leave
=
Available Balance
```

Balance adjustments must be auditable.

---

# 38. Standard Leave Approval Flow

Team Member:

```text
Employee
   ↓
Team Leader
   ↓
Operation Manager
   ↓
HR / Head HR
```

Once Team Leader and Operation Manager both approve:

```text
Management Approved
```

Final approval still belongs to HR/Head HR.

---

# 39. Leave Status

```text
DRAFT
SUBMITTED
TEAM_LEADER_APPROVED
OPERATION_MANAGER_APPROVED
HR_APPROVED
REJECTED
CANCELLED
```

---

# 40. Special Direct Leave Approval

Admin and Head HR can approve directly for exceptional purposes.

Requires:

* reason;
* bypassed stages;
* approving person;
* timestamp.

---

# 41. Senior Employee Leave Flow

| Employee Role     | Default Flow     |
| ----------------- | ---------------- |
| Team Member       | TL → OM → HR     |
| Team Leader       | OM → HR          |
| Operation Manager | Head HR          |
| HR                | Head HR          |
| Head HR           | Admin            |
| Admin             | Authorized Admin |

---

# 42. Overtime Management

Overtime is part of V1.

Two supported models:

```text
DAY_BASED
HOURLY
```

---

# 43. Default Overtime Configuration

Default:

```text
Overtime:
ENABLED

Weekend Overtime:
ENABLED

Holiday Overtime:
ENABLED

Weekend/Holiday Mode:
DAY_BASED

Hourly Overtime:
DISABLED
```

---

# 44. Weekend Overtime

If the employee works during an official weekend:

```text
Weekend
+
Valid Attendance
=
Overtime Candidate
```

After approval:

```text
1 Full Overtime Day
=
1 Additional Daily Salary
```

---

# 45. Holiday Overtime

If the employee works on an official holiday:

```text
Holiday
+
Valid Attendance
=
Holiday Overtime Candidate
```

After approval:

```text
1 Overtime Day
=
1 Additional Daily Salary
```

---

# 46. Overtime Minimum Working Duration

Admin controls how much work qualifies as a full overtime day.

Default example:

```text
Full Overtime Day:
8 hours
```

For V1:

```text
Half-Day Overtime:
OFF
```

Future configuration may allow:

```text
4 hours
=
0.5 day
```

---

# 47. Hourly Overtime

Hourly overtime architecture must exist but remain:

```text
OFF BY DEFAULT
```

Admin/Super Admin can enable it globally.

When disabled:

```text
Extra Hours
can be recorded

but

Hourly Overtime Payment
=
0
```

---

# 48. Hourly Overtime Configuration

When enabled, Admin can choose:

```text
FIXED_HOURLY_RATE
```

or:

```text
SALARY_DERIVED_RATE
```

Example:

```text
Monthly Salary
÷
Salary Days
÷
Daily Working Hours
=
Hourly Rate
```

Optional multiplier:

```text
1.0x
1.5x
2.0x
```

Default:

```text
1.0x
```

---

# 49. Overtime Eligibility

Employee setting:

```text
Overtime Eligible:
YES / NO
```

---

# 50. Overtime Approval Flow

Default:

```text
Overtime Detected
       ↓
Team Leader
       ↓
Operation Manager
       ↓
HR
       ↓
Payroll Eligible
```

Admin and Head HR may directly approve with exceptional authority.

---

# 51. Overtime Status

```text
DETECTED
PENDING_TEAM_LEADER
PENDING_OPERATION_MANAGER
PENDING_HR
APPROVED
REJECTED
PAYROLL_PROCESSED
```

---

# 52. Overtime and Attendance Relationship

Attendance proves work.

Attendance does not automatically authorize payment.

Correct architecture:

```text
Attendance
   ↓
Overtime Detection
   ↓
Approval
   ↓
Payroll
```

---

# 53. Shift Override and Overtime

Changing someone's working shift does not automatically mean overtime.

Example:

```text
Normal:
09:00–18:00

Temporary:
12:00–21:00
```

This is simply:

```text
Shift Override
```

Overtime rules are evaluated independently.

---

# 54. Holiday Calendar

Maintain holidays manually in V1.

Fields:

* title;
* date;
* type;
* description;
* office/location if needed;
* active status.

Future government calendar/API integration is outside V1.

---

# 55. Five-Day Holiday Reminder

Laravel Scheduler runs once per day.

When a holiday is five days away:

```text
Holiday Detected
      ↓
Reminder Created
      ↓
Head HR Reviews
      ↓
Head HR Approves
      ↓
Notice Generated
      ↓
Announcement Published
      ↓
Employees Receive Email
```

No notice is automatically published without Head HR approval.

---

# 56. Holiday Notice

Notice includes:

* company logo;
* title;
* holiday;
* date;
* message;
* closure information;
* return date;
* Head HR signature;
* generation date.

V1 stores files using Laravel private/local storage.

---

# 57. Announcement Module

Announcement types:

```text
GENERAL
HR_NOTICE
HOLIDAY
PAYROLL
POLICY
EMERGENCY
TEAM
```

Audience can be:

* all employees;
* department;
* team;
* role;
* selected employees.

Announcement fields:

* title;
* content;
* attachment;
* audience;
* publish date;
* expiry date;
* creator.

---

# 58. Payroll Management

Payroll supports:

* employee salary;
* salary components;
* allowances;
* bonuses;
* overtime;
* late penalties;
* absence deductions;
* unpaid leave;
* manual addition;
* manual deduction;
* payslip;
* employee acknowledgement.

---

# 59. Salary Structure

Salary can contain:

```text
Basic Salary
Housing Allowance
Medical Allowance
Transport Allowance
Other Allowance
```

Salary changes need an effective date.

Never overwrite historical salary information.

---

# 60. Attendance Late Penalty Rules

Attendance lateness and salary deduction must remain separate.

Example:

```text
Shift:
09:00

Grace:
10 Minutes

Employee:
09:09

Result:
NOT LATE
```

No late deduction can apply.

Example:

```text
Employee:
09:20

Result:
LATE
```

Payroll may then evaluate the company's late policy.

---

# 61. Late Penalty Configuration

Admin/HR should be able to configure payroll punishment separately.

Examples:

```text
3 Late Days:
Warning Only
```

```text
5 Late Days:
0.5 Day Salary Deduction
```

or:

```text
Each Qualified Late:
Fixed Deduction
```

V1 should not hard-code a particular late punishment.

Manual HR control must exist.

---

# 62. Important Grace-Period Rule

Changing:

```text
Late Grace Period
```

does **not** automatically change:

```text
Late Penalty Policy
```

These are separate settings.

Example:

```text
Grace:
20 Minutes

Late Penalty:
5 Late Occurrences = 0.5 Day
```

This means only check-ins after the 20-minute grace count toward late occurrences.

---

# 63. Payroll Month Configuration

Admin/Super Admin controls payroll cutoff.

## Standard

```text
August:
1 August → 31 August
```

## Custom

```text
Cutoff:
25
```

produces:

```text
August:
26 July → 25 August

September:
26 August → 25 September

October:
26 September → 25 October
```

---

# 64. Payroll Period

Create actual payroll period records.

Example:

```text
August 2026

Start:
2026-07-26

End:
2026-08-25
```

Statuses:

```text
UPCOMING
OPEN
PROCESSING
REVIEW
EMPLOYEE_CONFIRMATION
FINALIZED
PAID
LOCKED
```

Historical periods must not be changed by future payroll cutoff updates.

---

# 65. Daily Salary Calculation

Admin controls salary-day calculation method.

V1 options:

```text
FIXED_30_DAYS
CALENDAR_DAYS
WORKING_DAYS
```

Example:

```text
Salary:
30,000

Method:
FIXED_30_DAYS

Daily Salary:
1,000
```

Used for:

* unauthorized absence;
* unpaid leave;
* overtime day payment;
* other daily adjustments.

---

# 66. Payroll Formula

Conceptual:

```text
Gross Earnings
=
Base Salary
+ Allowances
+ Overtime
+ Bonus
+ Manual Earnings
```

```text
Total Deductions
=
Late Penalty
+ Unauthorized Absence
+ Unpaid Leave
+ Manual Deduction
+ Other Deduction
```

```text
Net Salary
=
Gross Earnings
-
Total Deductions
```

Always use decimal arithmetic.

---

# 67. Day-Based Overtime Formula

```text
Daily Salary
×
Approved Overtime Days
=
Overtime Earnings
```

Example:

```text
Daily Salary:
1,000

Weekend Overtime:
1 Day

Holiday Overtime:
1 Day

Total Overtime:
2,000
```

---

# 68. Manual Payroll Control

Authorized HR users can:

* add earning;
* add deduction;
* waive penalty;
* adjust attendance penalty;
* add overtime adjustment;
* add bonus.

Every adjustment requires:

```text
Reason
Previous Value
New Value
Changed By
Changed At
```

---

# 69. Payroll Workflow

```text
Payroll Period Opens
        ↓
HR Generates Payroll
        ↓
System Calculates Draft
        ↓
HR Reviews
        ↓
Manual Adjustments
        ↓
Payroll Prepared
        ↓
Employee Views Salary
        ↓
Employee Confirms / Disputes
        ↓
HR Resolves
        ↓
Finalize
        ↓
Paid
        ↓
Locked
```

---

# 70. Employee Payroll Confirmation

Employee receives:

```text
[ Confirm Salary ]
[ Report an Issue ]
```

Statuses:

```text
PENDING
ACKNOWLEDGED
DISPUTED
RESOLVED
```

Employee acknowledgement must not modify payroll numbers.

---

# 71. Payslip

Payslip includes:

* company;
* employee;
* payroll period;
* base salary;
* allowances;
* weekend overtime;
* holiday overtime;
* hourly overtime if enabled;
* bonus;
* late deductions;
* absence deductions;
* unpaid leave;
* manual adjustments;
* gross;
* deductions;
* net salary;
* payment status.

Store PDF in Laravel private/local storage.

---

# 72. Late Overtime Approval

Approved overtime should not silently modify a finalized payroll.

Example:

```text
Overtime Work:
25 August

Payroll Finalized:
26 August

Overtime Approved:
28 August
```

Result:

```text
Overtime Arrear
→
September Payroll
```

---

# 73. Team Member Dashboard

Display:

* today's shift;
* grace end time;
* check-in;
* checkout;
* current attendance;
* worked hours;
* attendance history;
* leave balance;
* pending leave;
* overtime status;
* holidays;
* announcements;
* payroll;
* payslip.

Example attendance card:

```text
Shift:
09:00 AM – 06:00 PM

Flexible Check-In Until:
09:10 AM

Checked In:
09:07 AM

Status:
On Time
```

---

# 74. Team Leader Dashboard

Additional:

* team attendance;
* late employees;
* absent employees;
* employees on leave;
* pending leave approvals;
* overtime approvals;
* missing checkouts;
* team statistics.

---

# 75. Operation Manager Dashboard

Additional:

* multiple teams;
* team comparison;
* attendance;
* leave approvals;
* overtime approvals;
* employees currently available.

---

# 76. HR Dashboard

Display:

* employee count;
* attendance today;
* late employees;
* absent employees;
* leave;
* attendance corrections;
* leave approvals;
* overtime approvals;
* holidays;
* announcements;
* payroll preparation;
* salary disputes.

---

# 77. Head HR Dashboard

Include:

* organization HR analytics;
* final leave approvals;
* overtime summary;
* holiday notices awaiting approval;
* payroll overview;
* attendance trends.

---

# 78. Admin Dashboard

Include:

* total workforce;
* organization hierarchy;
* attendance;
* leave;
* overtime;
* payroll;
* settings;
* departments;
* teams;
* HR activities.

Admin settings should prominently include:

```text
Attendance Settings
    ↓
Late Grace Period

Payroll Settings
    ↓
Payroll Cutoff
Late Penalty Rules
Overtime Rules
```

---

# 79. Basic Technical Dashboard

Laravel may have a minimal technical interface for System Admin/DevOps.

Display:

```text
Application Version
Environment
Laravel Version
PHP Version
Database Status
Local Storage Status
Scheduler Heartbeat
Database Queue Jobs
Failed Jobs
Recent Errors
```

Do not add Redis/Horizon/Pulse.

---

# 80. Notification Channels

V1:

```text
IN_APP
EMAIL
```

Events include:

* leave submitted;
* leave approved;
* leave rejected;
* leave cancelled;
* overtime pending;
* overtime approved;
* overtime rejected;
* shift changed;
* holiday notice;
* announcement;
* payroll ready;
* payroll dispute raised;
* payroll dispute resolved;
* attendance corrected — the employee whose record changed must be told (§32);
* missing checkout — to the employee and their Team Leader (§137);
* invitation sent / invitation reminder (§148);
* leave balance adjusted (§37);
* payroll arrear applied (§146).

Rules:

* every notification is written `IN_APP` first; `EMAIL` is layered on top for events the
  employee cannot afford to miss — approvals, payroll, holiday notices, invitations;
* bulk sends go through the database queue (§81), never inline in a request;
* the nightly close (§137) sends HR **one summary**, not one notification per employee;
* an employee always receives a notification when someone else changes their attendance,
  leave, salary, or payroll. Silent edits to a person's own record are not acceptable in an
  HR system;
* notification preferences are out of V1 scope — state it rather than half-build it.

---

# 81. Queue Strategy

Use Laravel database queue only when necessary.

Suitable examples:

* sending email to all employees;
* batch payslip generation;
* notice emails.

Do not add Redis to V1.

---

# 82. File Storage

Use:

```text
Laravel Private Local Storage
```

for:

* employee files;
* signatures;
* payslips;
* holiday notices;
* announcement attachments.

Private files must be served through authorized backend endpoints.

Future:

```text
Local Storage
→
S3-Compatible Storage
```

---

# 83. Audit Logs

Sensitive actions must be audited.

Record:

```text
user_id
action
entity_type
entity_id
old_data
new_data
reason
ip_address        ← added: "who changed this salary" is incomplete without it
user_agent        ← added
created_at
```

Events:

```text
ATTENDANCE_UPDATED
ATTENDANCE_GRACE_CHANGED
SHIFT_CHANGED
LEAVE_APPROVED
LEAVE_REJECTED
OVERTIME_APPROVED
OVERTIME_ADJUSTED
SALARY_CHANGED
PAYROLL_ADJUSTED
PAYROLL_FINALIZED
PAYROLL_SETTINGS_CHANGED

EMPLOYEE_STATUS_CHANGED       ← §13 transitions (§84)
LEAVE_BALANCE_ADJUSTED        ← §37 manual adjustments
PAYROLL_DISPUTE_RAISED        ← §147
PAYROLL_DISPUTE_RESOLVED      ← §147
PAYROLL_ARREAR_CREATED        ← §146
PAYROLL_ARREAR_APPLIED        ← §146
ROLE_ASSIGNED                 ← §10 scope grants are security events
PERMISSION_CHANGED
USER_TOKENS_REVOKED           ← §92.2
LOGIN_FAILED                  ← repeated failures are the signal worth keeping
REPORT_EXPORTED               ← §11 report.export; payroll data leaving the system
DOCUMENT_DOWNLOADED           ← §82 private file access
```

Audit rows are **append-only**. No endpoint updates or deletes one, and no role holds a
permission to. `audit.view` is read-only by construction.

An audit entry is written inside the same database transaction as the change it records,
so a rolled-back change cannot leave a phantom audit row — and a successful change can
never lack one.

---

# 84. Suggested Core Database Tables

**Framework tables — already migrated, do not recreate:**

```text
users                      0001_01_01_000000
password_reset_tokens      0001_01_01_000000
sessions                   0001_01_01_000000   ← needed by the §79 Inertia console
cache / cache_locks        0001_01_01_000001
jobs / job_batches         0001_01_01_000002   ← database queue (§81)
failed_jobs                0001_01_01_000002   ← surfaced by §79
users.two_factor_*         2025_08_14_170933   ← Fortify 2FA columns
```

Phase 0 adds `personal_access_tokens` via `php artisan vendor:publish` for Sanctum (§92).

**HR domain tables:**

```text
roles
permissions
role_permissions
user_roles                 carries the scope — see note below

employees
employee_status_history    ← §13 status transitions must be auditable

departments
teams
team_members               ← keep from/to dates: §14 requires assignment history

organization_settings

shifts
employee_shifts
shift_overrides

attendance_events
attendance_records
attendance_adjustments

leave_types
leave_policies
leave_balances
leave_balance_transactions
leave_requests
leave_approvals

overtime_settings
overtime_records
overtime_approvals

holidays
holiday_reminders          ← §55 five-day reminder needs state to avoid re-firing
holiday_notices            ← §56 describes a notice document with no table

announcements
announcement_targets
announcement_reads         ← acknowledgement of EMERGENCY/POLICY notices

salary_structures
salary_components
employee_salaries

payroll_settings
payroll_periods
payroll_runs
payroll_entries
payroll_entry_lines        ← §66/§71 itemisation; an entry alone cannot show a payslip
payroll_adjustments
payroll_disputes           ← §70 DISPUTED/RESOLVED had no table
payroll_arrears            ← §72 late-approved overtime carried to the next period

payslips

notifications              standard Laravel notifications table
documents
audit_logs
```

**Tables added over v3.0 and why:**

| Table | Gap it closes |
| --- | --- |
| `employee_status_history` | §13 defines eight statuses. Transitions between them (suspension, resignation, termination) are exactly the events an HR audit asks about, and overwriting a status column destroys that history. |
| `holiday_reminders` | §55's scheduler runs daily. Without persisted reminder state it re-creates the same reminder every day for five days, and re-notifies Head HR each time. |
| `holiday_notices` | §56 specifies a notice with a signature, closure info, return date, and a stored PDF. That is an entity, not a field on `holidays`. |
| `announcement_reads` | §57 supports EMERGENCY and POLICY announcements. "Who has seen the policy" is the only reason to send one. |
| `payroll_entry_lines` | §66 sums five earning types and five deduction types; §71 requires every one itemised on the payslip. Flat columns on `payroll_entries` cannot represent an arbitrary number of manual adjustments (§68). |
| `payroll_disputes` | §70 defines PENDING/ACKNOWLEDGED/DISPUTED/RESOLVED and a "HR Resolves" step (§69) with nowhere to record the dispute, its reason, or its resolution. |
| `payroll_arrears` | §72 requires August overtime approved after finalisation to be paid in September. Without a carry-forward record that money is silently lost — this is the most consequential omission in v3.0. |

`user_roles` must carry the scope (§10). A user may hold the same role at different scopes,
and Team Leader of Team A must not read Team B, so scope belongs on the assignment, not on
the role.

All money columns are `DECIMAL(15,4)` — see §141.

---

# 85. Organization Settings

Recommended central settings:

```text
company_name
company_logo_path
timezone                          the single work timezone — see §142
currency
currency_decimal_places

late_grace_minutes                §16–§22

weekend_days                      e.g. ["friday","saturday"]

default_shift_id

payroll_cutoff_day                §63
salary_day_calculation_method     FIXED_30_DAYS | CALENDAR_DAYS | WORKING_DAYS

overtime_enabled
weekend_overtime_enabled
holiday_overtime_enabled
hourly_overtime_enabled
overtime_full_day_minutes         §46 — "8 hours" must be configuration
overtime_daily_salary_basis       BASIC | GROSS — §143
overtime_hourly_rate_mode         FIXED | SALARY_DERIVED — §48
overtime_hourly_fixed_rate
overtime_hourly_multiplier        default 1.0

auto_absent_enabled               §137 nightly close
missing_checkout_policy           §137
attendance_min_minutes_half_day   §29 HALF_DAY had no producing rule

leave_year_start_month            §144 accrual
leave_carry_forward_cap_days
```

Every one of these is read at evaluation time through a settings service. Nothing in this
list may appear as a literal in application code (§125).

Do not put every business rule into one JSON column.

Use structured tables/configuration where the rule becomes complex. In particular, late
penalty rules (§61) and leave policies (§36) are **tables**, not settings keys — they have
thresholds, effective dates, and history.

---

# 86. Attendance API

Examples:

```text
GET /api/v1/attendance/today

POST /api/v1/attendance/check-in

POST /api/v1/attendance/check-out

GET /api/v1/attendance

PATCH /api/v1/attendance/{id}/adjust
```

---

# 87. Attendance Settings API

Admin endpoints:

```text
GET /api/v1/settings/attendance

PUT /api/v1/settings/attendance
```

Example request:

```json
{
  "late_grace_minutes": 10
}
```

Laravel validates and stores the setting.

---

# 88. Shift API

```text
GET /api/v1/shifts
POST /api/v1/shifts
PUT /api/v1/shifts/{id}

POST /api/v1/shift-overrides
```

Optional shift-level grace override:

```json
{
  "name": "Night Shift",
  "start_time": "20:00",
  "end_time": "05:00",
  "late_grace_minutes": 20
}
```

`null` means use global grace period.

---

# 89. Leave API

```text
/api/v1/leave-types
/api/v1/leave-requests

/api/v1/leave-requests/{id}/approve
/api/v1/leave-requests/{id}/reject
```

---

# 90. Overtime API

```text
GET /api/v1/overtime

GET /api/v1/overtime/{id}

POST /api/v1/overtime/{id}/approve

POST /api/v1/overtime/{id}/reject

PATCH /api/v1/overtime/{id}/adjust
```

---

# 91. Payroll API

```text
/api/v1/payroll/settings

/api/v1/payroll/periods

/api/v1/payroll/runs

/api/v1/payroll/entries

/api/v1/payroll/entries/{id}/adjust

/api/v1/payroll/entries/{id}/release

/api/v1/payroll/entries/{id}/acknowledge

/api/v1/payroll/entries/{id}/dispute
```

---

# 92. Authentication

## 92.1 Model

Two authentication surfaces, one user table:

```text
Next.js  (all HR features)   →  Sanctum Bearer personal access tokens
/system  (§79 console only)  →  Fortify session cookies
```

Fortify is already installed and provides login, logout, registration, password reset,
email verification, password confirmation, and TOTP two-factor. Phase 0 does **not**
rebuild these — it exposes them as JSON under `/api/v1/auth/*` and issues a Sanctum token
on successful authentication.

## 92.2 Token Flow

```text
POST /api/v1/auth/login
     { email, password, device_name }
        ↓
     2FA enabled?  →  202 { two_factor: true, challenge_id }
        ↓                    POST /api/v1/auth/two-factor-challenge
     200 { token, user, permissions[], expires_at }
        ↓
Next.js sends  Authorization: Bearer <token>  on every request
        ↓
POST /api/v1/auth/logout   revokes the current token only
```

Rules:

* one token per device; `device_name` is required so a user can revoke a single device;
* tokens expire — set `SANCTUM_TOKEN_EXPIRATION`; a 90-day default for V1;
* a token carries **no** abilities in V1 (`['*']`). Authorization is decided by policies
  against the user's roles/permissions/scope (§10), never by token abilities. Two
  authorization systems would inevitably disagree;
* changing a password, suspending, or archiving an employee revokes **all** that user's
  tokens immediately;
* the login response includes the user's resolved permission list purely so the UI can
  hide controls. It is a convenience, not a security boundary.

## 92.3 Token Storage in Next.js

The token must not be readable by injected script where avoidable. V1 approach:

```text
Next.js Route Handler  →  holds the Sanctum token in an httpOnly cookie
                          and proxies /api/v1/* server-side
```

This keeps the Bearer token out of `localStorage` and out of client JavaScript, while the
backend still speaks pure token auth. If the proxy proves impractical, in-memory storage
with a silent re-login on reload is the fallback — **`localStorage` is not acceptable for
an application holding salary data.**

## 92.4 Registration Must Be Closed

`config/fortify.php` currently enables `Features::registration()`. On a live HRM this lets
anyone on the internet self-register an account. Phase 1 must:

```text
REMOVE  Features::registration()
KEEP    Features::resetPasswords()
KEEP    Features::emailVerification()
KEEP    Features::twoFactorAuthentication(confirm: true, confirmPassword: true)
```

Employees enter the system through the §13 `INVITED` flow — HR creates the employee record
and Laravel emails a signed, expiring invitation that sets the first password. Self-service
registration never exists.

## 92.5 Two-Factor and Passkeys

The scaffold ships TOTP two-factor (Fortify). `laravel/passkeys` is present only as a
transitive dependency of Fortify 1.39+ — it is not in `composer.json` directly, and its
migration has not been published, so no `passkeys` table exists. v3.0 mentioned neither
capability.

* **2FA** — optional for all employees; **mandatory** for any role holding
  `payroll.finalize`, `employee.financial.manage`, or `settings.manage`;
* **Passkeys** — not adopted for V1. Publishing its migration and building the enrollment
  UI is real work for a capability nobody asked for; leave it dormant as a transitive
  dependency. Revisit only if a future phase specifically wants WebAuthn.

Never treat hidden frontend buttons as security.

Laravel policies are mandatory.

---

# 93. Security Requirements

V1 must implement:

**Authentication and session**

* Sanctum Bearer tokens for the API, with expiry and per-device revocation (§92);
* session cookies for the `/system` console only, `httpOnly` + `secure` + `SameSite=Lax`;
* **CSRF protection applies to the `/system` console and Fortify's web routes.** It does
  **not** apply to Bearer-token API requests — a stateless token endpoint has no ambient
  credential to forge. v3.0 listed CSRF as a blanket requirement; that was written for
  cookie-based SPA auth and does not describe this design;
* bcrypt at 12 rounds (already configured);
* login throttling — Fortify's limiter, plus a stricter limit on 2FA challenges;
* token revocation on password change, suspension, and archival.

**Transport and origin**

* HTTPS in every environment above local;
* CORS allow-list naming the exact frontend origin — never `*`. `Authorization` must be an
  allowed header. Credentials are not required for token auth;
* no API response may include `Access-Control-Allow-Origin: *` alongside employee data.

**Authorization**

* every endpoint carries a policy check; there is no "trusted internal" route;
* scope resolution goes through one service (§10) so team boundaries cannot drift;
* payroll permission separation — preparing, adjusting, and finalising are distinct grants,
  and financial fields on an employee are gated separately from the profile (§11);
* mass-assignment protection on every model; salary fields are never fillable from request
  input without an explicit financial permission check.

**Data**

* request validation via Form Requests on every write;
* rate limiting on attendance check-in/check-out (§28) and on report export;
* private files served only through authorized controller endpoints (§82), never by URL;
* audit records for every sensitive action (§83), including IP and user agent.

**Explicitly out of V1 scope** (state it rather than leave it ambiguous): statutory tax and
provident-fund calculation, payroll bank-file export, SSO/SAML, and data-residency controls.

---

# 94. Timezone Rules

Database timestamps should use UTC.

Organization contains:

```text
timezone
```

Attendance calculations convert timestamps to organization/work timezone.

---

# 95. Historical Attendance Integrity

Attendance should store enough snapshot information to reproduce why someone was marked late.

Recommended:

```text
shift_start_used
shift_end_used
grace_minutes_used
check_in_time
late_minutes
status
```

Example historical record:

```text
Date:
August 20

Shift Start Used:
09:00

Grace Used:
10

Check-In:
09:09

Status:
PRESENT
```

Changing the current grace setting to 20 minutes should not alter this record.

---

# 96. Grace Period Edge Cases

## Exactly at grace end

```text
Shift:
09:00

Grace:
10

Check-In:
09:10
```

Result:

```text
ON TIME
```

## One minute after

```text
Check-In:
09:11
```

Result:

```text
LATE
```

## No grace

```text
Grace:
0

Shift:
09:00

09:00:
ON TIME

09:01:
LATE
```

---

# 97. Grace Period and Manual Attendance

If HR manually changes check-in:

```text
09:15
→
09:08
```

and grace is:

```text
10 minutes
```

Laravel must recalculate attendance:

```text
LATE
→
PRESENT / ON TIME
```

The correction must be logged.

---

# 98. Grace Period and Temporary Shift

Employee:

```text
Normal Shift:
09:00
```

Temporary shift:

```text
12:00
```

Global grace:

```text
10 minutes
```

The employee remains on time until:

```text
12:10
```

because attendance must evaluate the actual shift assigned for that date.

---

# 99. Reports

V1 reports:

* employee directory;
* attendance;
* late attendance;
* absence;
* leave;
* leave balance;
* overtime;
* payroll;
* payroll deductions.

Attendance report should include:

```text
Employee
Date
Shift
Shift Start
Grace Minutes
Grace End Time
Actual Check-In
Status
Late Minutes
```

---

# 100. Frontend Attendance Settings

Admin UI:

```text
Settings
   ↓
Attendance
```

Display:

```text
Late Grace Period

[ 10 ] minutes
```

Helper:

```text
Employees checking in within this period
after their shift starts will not be marked late.
```

Preview:

```text
Example Shift Start:
09:00 AM

Current Grace:
10 minutes

Employees are considered on time
through 09:10 AM.

Late starts after:
09:10 AM
```

This preview reduces configuration mistakes.

---

# 101. PHASE 0 — Foundation

Phase 0 is **scaffold alignment**, not greenfield setup. Both applications already exist as
untouched starter templates; this phase converts them into the architecture this PRD
describes. No HR domain code is written here.

## Backend

```text
☑ Switch DB_CONNECTION from sqlite to mysql in .env and .env.example
      DB_HOST=127.0.0.1  DB_PORT=3306  DB_DATABASE=hrms  DB_USERNAME=root
      MySQL 9.7.1 is running via DBngin; the `hrms` database does not exist yet:
      CREATE DATABASE hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
☑ Switch phpunit.xml off sqlite/:memory: onto a dedicated hrms_test MySQL database
      (§140 — money and date semantics must match production)
☑ Delete database/database.sqlite and untrack it
☑ composer require laravel/sanctum; publish personal_access_tokens migration
☑ Add routes/api.php and register api: + apiPrefix 'api/v1' in bootstrap/app.php (§5.2)
☑ Configure CORS for the Next.js origin (§93)
☑ Reduce the Inertia layer to the /system console; delete settings/ and welcome pages (§5.1)
☑ Remove Features::registration() from config/fortify.php (§92.4)
☑ Expose Fortify auth as JSON under /api/v1/auth/* and issue Sanctum tokens (§92.2)
☑ Add app/Enums, app/Services, app/Policies, app/Support directories (§5.3)
☑ Confirm the local (private) disk and add a storage/app/private/.gitignore
☑ MAIL_MAILER stays `log` for local dev (correct, verified) — real SMTP
      credentials are a deployment-time config, not a Phase 0 blocker
☑ Register the scheduler and verify one cron entry runs (§137 depends on it)
☑ Standardise the JSON envelope and error shape (§139)
☑ Keep /up as the basic health endpoint
```

## Frontend

```text
☑ Install Mantine, TanStack Query, Zod, Tabler icons, dayjs, recharts, Playwright (§6.0)
☑ Configure PostCSS for Mantine + Tailwind, disable Tailwind Preflight (§6.2)
☑ MantineProvider + ColorSchemeScript + QueryClientProvider in app/layout.tsx
☑ Create .env.example with NEXT_PUBLIC_API_URL
☑ Build lib/api-client.ts — Bearer attach, §139 envelope unwrap, 401 → logout
☑ Build the authenticated shell: collapsible sidebar, header, page-header primitive (§7)
      — real login/session gating is Phase 1's job once auth pages exist
☑ Global error boundary, loading skeletons, empty-state component
☑ Read node_modules/next/dist/docs/ before writing routing or data-fetching code (§6.1)
☑ Scaffold the features/ and components/ tree (§6.3)
☑ One Playwright smoke test: app boots, shell renders (no login page exists
      yet — Phase 1 should extend this test once it builds one)
```

## Repository

```text
☑ Copy or symlink docs/PRD.md into both backend/docs/ and frontend/docs/ (§3) —
      it currently lives outside both repos and isn't version-controlled anywhere
☑ Author architecture.md, database.md, permissions.md, api.md, add them to both repos (§3)
☑ Add a README.md to each repo (hrms_backend, hrms_frontend)
☑ Commit backend Phase 0 changes to hrms_backend, frontend Phase 0 changes to
      hrms_frontend — separately, each with its own commit(s)
```

**Phase 0 status: complete, 2026-08-27.** All items above are done and verified —
backend tests (54 Pest tests, PHPUnit runner) pass against MySQL, `vendor/bin/phpstan`
and `vendor/bin/pint` are clean, the frontend builds/lints/type-checks clean and its one
Playwright spec passes, and the login → token → authenticated-request → logout flow was
smoke-tested end-to-end against the real backend. Neither repo has been pushed to its
GitHub remote yet — that's a deliberate pause point, not an oversight.

**Exit condition:** Next.js can call an authenticated `/api/v1` endpoint with a Bearer
token, render the response inside the Mantine shell, and `php artisan test` passes green
against MySQL.

**Dependency:** None.

---

# 102. PHASE 1 — Authentication and RBAC

Implement:

* ☑ users;
* ☑ login/logout;
* ☑ password reset;
* ☑ roles;
* ☑ permissions;
* ☑ scopes;
* ☑ Laravel Policies;
* ☑ protected Next.js routes.

**Phase 1 status: complete, 2026-08-27.** Backend: `roles`/`permissions`/
`role_permissions`/`user_roles` tables, `Scope` and `PermissionName` backed enums,
`RolePermissionSeeder` (7 roles, full §11 permission list, a default capability set per
role), `$user->hasPermission()`/`hasRole()` wired into Laravel's Gate via `Gate::before`,
`UserRolePolicy` plus `/api/v1/users/{user}/roles` to grant/revoke, `hrm:install` to
bootstrap the first Admin (§148 #1), `/api/v1/auth/{forgot-password,reset-password}`
reusing Fortify's own password-reset action, and `/auth/me` + the login response now
include the caller's roles and resolved permissions. Frontend: the Sanctum token lives in
an httpOnly cookie set by a Next.js Route Handler proxy (§92.3) — a real login page with
the 2FA-challenge branch, forgot/reset-password pages, `(dashboard)/layout.tsx` redirects
unauthenticated visitors to `/login`, `/login` redirects an already-authenticated visitor
back — all verified with Playwright against the real backend, not mocks. 86 backend tests
pass on MySQL, `vendor/bin/phpstan`/`pint` clean, frontend builds/lints/type-checks clean.
Both repos pushed; backend CI green.

Scope resolution — turning a permission grant into an actual employee-ID set — is
deliberately **not** built yet: `scopesFor()` on `HasRoles` exposes the grants (permission,
scope, scope_id) for a future `ScopeResolver` to consume, but there is no `Employee`,
`Team`, or `Department` table for it to resolve against until Phase 2. Don't be surprised
that a `TEAM`-scoped grant doesn't yet filter anything — that's Phase 2's job.

**Dependency:** Phase 0.

---

# 103. PHASE 2 — Employees and Organization

Implement:

* ☑ employee profiles;
* ☑ departments;
* ☑ teams;
* ☑ Team Leaders;
* ☑ Operation Managers;
* ☑ team membership;
* ☑ hierarchy.

Completion condition:

```text
Laravel can determine
who manages each employee.
```

**Phase 2 status: complete, 2026-08-27.** `employees`/`departments`/`teams`/`team_members`/
`employee_status_history` tables. department/team/team-leader/operation-manager are
deliberately *not* columns on `employees` — they're derived by walking
`team_members → teams → departments` (`Employee::currentTeam()`/`teamLeader()`/
`operationManager()`), so a transfer can never leave two contradictory sources of truth;
`team_members` keeps `started_at`/`ended_at` instead of being deleted on transfer, so
history survives (§14). `ScopeResolver` (stubbed in Phase 1) now actually resolves
TEAM/DEPARTMENT/OPERATION into real employee-ID sets; HR_SCOPE simplifies to unrestricted
for V1 since no HR-territory table exists. `EmployeeService::invite()` creates the paired
User account and emails an invitation; accepting it turned out to be the same action as
resetting a forgotten password (setting one), so `NewPasswordController` just checks
whether the account was still INVITED and transitions it — no separate accept-invitation
endpoint exists despite §139.6 originally listing one (removed there and from `api.md`).
Full CRUD + transfer + membership endpoints and policies; out-of-scope and no-permission
both return 404 on a specific employee, never 403, matching §139.2. Frontend: searchable
employee table, invite form, detail page with transfer/status controls, department/team
management with member add/remove — all Playwright-verified against the real backend, not
mocks. 134 backend tests pass on MySQL, phpstan/pint clean; frontend builds/lints clean.
Both repos pushed; backend CI green.

Two real bugs were caught by testing against the live app rather than trusting types:
a freshly created department/team showed `active: null` in the API response instead of
the database's actual default (`true`) — Eloquent doesn't hydrate a mass-assigned model's
absent attributes after `create()`, fixed by mirroring the column default on the model
(`protected $attributes`). And Mantine's `DateInput` returns a `"YYYY-MM-DD"` string, not
a `Date` — the joining-date field was validated against `z.date()` and silently rejected
every value despite displaying it correctly.

**Dependency:** Phase 1.

---

# 104. PHASE 3 — Organization Settings, Shifts and Attendance Policies

This phase now includes the configurable late grace feature.

Implement:

* ☑ organization timezone;
* ☑ organization currency;
* ☑ weekend configuration;
* ☑ default shift;
* ☑ shifts;
* ☑ temporary shift changes;
* ☑ global late grace period;
* ☑ optional shift-specific grace override;
* ☑ overtime global settings;
* ☑ payroll cutoff setting;
* ☑ holiday calendar foundation.

Default:

```text
Late Grace:
10 minutes

Hourly Overtime:
OFF
```

Admin UI should include:

```text
Settings
→ Attendance
→ Late Grace Period
```

Completion condition:

Given an employee and date, backend can determine:

```text
Work Day?
Shift?
Shift Start?
Shift End?
Grace Minutes?
Grace End Time?
Holiday?
Weekend?
```

**Phase 3 status: complete, 2026-08-27.** Backend: `shifts`/`organization_settings`
(singleton, read through `OrganizationSettings::current()`)/`employee_shifts`/
`shift_overrides`/`holidays` tables. `ShiftService::resolveForDate()` is the completion
condition made literal — one method returning a `ShiftResolution` value object with
exactly the eight facts §104 asks for, given an employee and a date; a `shift_override` row
for that exact date always wins over the regular `employee_shifts` assignment without
touching it (§23), and an overnight shift's end time lands on the following calendar day
(§136). `employee_shifts`/`team_members` share the same started_at/ended_at-never-deleted
shape, so `EmployeeService::assignShift()` mirrors `transfer()` exactly. Settings are one
table split across four separately-permissioned endpoints (`/settings/organization`,
`/settings/attendance`, `/settings/overtime`, `/settings/payroll` — §139.6), each gated by
its own permission (`settings.manage`, `attendance.settings.manage`,
`overtime.policy.manage`, `payroll.settings.manage`) rather than one blanket check, since
those are genuinely different privileges (Head of HR holds all four; nobody else holds
any). `leave_year_start_month`/`leave_carry_forward_cap_days` already exist as columns on
the same table but aren't exposed by any endpoint yet — out of scope until Phase 5's
leave-policies work actually needs them. 173 backend tests pass on MySQL,
`vendor/bin/phpstan`/`pint` clean. Frontend: Settings page (tabbed Organization/
Attendance/Overtime/Payroll forms), Shifts catalogue (create/edit), Holiday calendar
(create/edit/delete), and — on the employee detail page — both "assign regular shift" and
"temporary shift change" actions, the latter mirroring the Transfer card. Build/lint/
type-check clean; every new endpoint was smoke-tested end to end through the real Next.js
proxy with a real session cookie against the live backend (login → create/read/update
across all five resources), not just unit-tested — but unlike Phase 1/2, this pass had no
Playwright/browser access, so the UI itself was not click-tested in a real browser this
time. That's worth doing before calling the frontend done, not just the wiring.

A real bug surfaced during that smoke test, not the test suite: `OrganizationSettings::
current()` originally cached the full Eloquent model object via `Cache::rememberForever`.
Against the `database` cache driver, a `rememberForever` entry never expires — so the very
next code change to the model's shape (this phase added one) leaves a previous deploy's
serialized object sitting in the `cache` table, unserializing as `__PHP_Incomplete_Class`
and 500ing every request that touches settings (i.e. almost everything) until someone
manually clears it. Fixed by caching `getAttributes()` — a plain array — and rehydrating
through `newFromBuilder()`, which has no class shape to go stale. Regression-tested by
seeding the cache with a raw attributes array directly and asserting `current()` still
resolves; the original failure mode (a genuinely incompatible serialized object) isn't
practical to reproduce in a test, but the fix removes the mechanism, not just this
instance of it.

**Dependency:** Phase 2.

---

# 105. PHASE 4 — Attendance

Implement:

* ☑ check-in;
* ☑ checkout;
* ☑ attendance events;
* ☑ daily attendance record;
* ☑ late calculation;
* ☑ grace handling;
* ☑ absence;
* ☑ missing checkout;
* ☑ manual correction;
* ☑ manager attendance view;
* ☑ attendance reports;
* ☑ weekend work detection;
* ☑ holiday work detection.

Mandatory rule:

```text
check_in <= shift_start + grace
=
NOT LATE
```

**Phase 4 status: complete, 2026-08-27.** Backend: `attendance_events` (append-only
punches)/`attendance_records` (one per employee per work_date, unique-constrained)/
`attendance_adjustments` (§32 correction history) tables. `AttendanceService` is the whole
domain: `today()` backs the check-in prompt (§137's suppression rules — never prompts on a
weekend/holiday/leave day or once a check-in exists), `checkIn()`/`checkOut()` are
idempotent (409 `ALREADY_CHECKED_IN` / `ALREADY_CHECKED_OUT` / `NOT_CHECKED_IN`, the
existing record riding in `data` per §139.5), and `adjust()` is §32/§97 manual correction —
recalculating late/status from the record's own already-snapshotted
`shift_start_used`/`grace_minutes_used`, never from live settings, so a later grace-setting
change can't retroactively rewrite history (§22/§95). §132's grace rule (`classify()`) is
one nine-line method, exercised against every §115/§96 boundary case (08:59 through 09:30,
grace at 0, exactly-at-grace-end).

§136's overnight-shift work-date rule is real, not aspirational: `checkIn()` resolves both
today's and yesterday's shift via `ShiftService`, and a punch attaches to whichever
`[shift_start − N, shift_end + N]` window contains it (today wins on overlap), rejecting
anything that fits neither with `422 OUTSIDE_CHECKIN_WINDOW` rather than silently guessing
a day. `N` is `attendance_checkin_window_minutes` (default 240) — a setting §136 requires
but §85's list never enumerated, so it's a new organization_settings column, same gap shape
as the rest of §134.3's table.

`attendance:close` (`CloseAttendanceCommand`, scheduled daily at 02:00 server time) is
§137's nightly job — the only thing that ever produces `ABSENT`/`MISSING_CHECKOUT`/
`HALF_DAY`/`WEEKEND`/`HOLIDAY`, since a check-in-born record only ever starts as
`PRESENT`/`LATE`. Idempotent, skips any record already flagged `is_manual_adjustment`,
honors `missing_checkout_policy` (`LEAVE_OPEN` vs `AUTO_CLOSE_AT_SHIFT_END`), reclassifies
a checked-out-but-short day to `HALF_DAY` against `attendance_min_minutes_half_day`, and
sends exactly one `AttendanceCloseSummary` database notification to every
`attendance.manage` holder — not one per employee. `ON_LEAVE` is a documented Phase 5 seam
(`hasApprovedLeave()` always false) — there's no `LeaveRequest` table yet, mirroring how
`ScopeResolver` stubbed `HR_SCOPE` before Department/Team existed. Frontend: a global
check-in dialog (mounted in the dashboard shell, §26) and a live today's-attendance card
(§27, worked-time ticking client-side) built on the new design system, plus a scoped
`/attendance` manager view (§33 filters, §99 report columns) with a manual-correction
dialog gated on `attendance.correct`. 220 backend tests pass, `phpstan`/`pint` clean;
frontend build/lint/type-check clean, and the full flow (check-in → duplicate rejection →
check-out → listing → correction → nightly close → HR notification) was smoke-tested
end-to-end through the real Next.js proxy against the live backend.

Two real bugs surfaced by that smoke test, not the test suite. First: `AttendanceService`
type-hinted `Illuminate\Support\Carbon` throughout, but this app configures
`Date::use(CarbonImmutable::class)` — every Eloquent-cast datetime read is actually a
`CarbonImmutable`, which isn't a subtype of the mutable class, so passing a freshly-read
`$record->check_in` into a `Carbon`-typed parameter threw a `TypeError` the moment
`adjust()` tried to reclassify a correction. Fixed by typing against `Carbon\CarbonInterface`
(what both classes implement) wherever a value might come from either construction path.
Second, an operational one: `organization_settings` is a `Cache::rememberForever`'d
singleton (§104's fix for a different caching bug), and a `rememberForever` entry never
expires — so a cache entry written before this phase's new
`attendance_checkin_window_minutes` column existed was simply missing that key, not
null-with-a-default, and every check-in threw until the cache was forgotten. Fixed by
having the migration itself forget the cache key; the same line belongs in any future
migration that touches this table. Neither would have been caught by a unit test running
against a fresh in-memory app boot, which is exactly why the live smoke-test pass matters.

Also fixed in passing: `EmployeeController::index()`'s status/team/department filters used
`$request->query('filter.status')`, which silently returns null against a real
`?filter[status]=X` request — Symfony's `ParameterBag::get()` has no dot-notation support,
unlike `$request->input()`, which resolves it through `data_get()`. No existing test ever
sent a bracket-array query string, so this had been a silent no-op since Phase 2.
`AttendanceController` was written the same way and hit the identical bug immediately;
both are now fixed to use `input()`. And: `/auth/me` and the login response now carry
`organization.timezone` — §142 makes the organization timezone authoritative for *display*,
not just evaluation, but `/settings/organization` is `settings.manage`-gated and most
employees reading a check-in time don't hold it, so it rides on the one request every
session already makes instead.

**Dependency:** Phase 3.

---

# 106. PHASE 5 — Leave

Implement:

* leave types;
* policy;
* balances;
* leave request;
* Team Leader approval;
* Operation Manager approval;
* HR approval;
* direct Head HR/Admin approval;
* cancellation;
* attendance integration.

**Dependency:** Phase 4.

---

# 107. PHASE 6 — Overtime

Implement:

* weekend overtime;
* holiday overtime;
* overtime eligibility;
* minimum duration;
* approvals;
* manual adjustments;
* hourly overtime architecture.

Default:

```text
Weekend/Holiday Day Overtime:
ON

Hourly Overtime:
OFF
```

**Dependency:** Phase 5.

---

# 108. PHASE 7 — Holidays and Announcements

Implement:

* holiday calendar;
* five-day reminder;
* Head HR approval;
* notice generation;
* signature;
* announcements;
* SMTP notifications.

Use Laravel Scheduler.

**Dependency:** Phase 6.

---

# 109. PHASE 8 — Payroll Foundation

Implement:

* salary components;
* employee salary;
* salary history;
* payroll settings;
* payroll periods;
* custom cutoff;
* salary divisor;
* late policies;
* absence policies;
* unpaid leave;
* overtime earnings;
* draft payroll calculation.

Completion:

```text
Salary
+
Attendance
+
Late Rules
+
Leave
+
Overtime
+
Adjustments
=
Draft Net Salary
```

**Dependency:** Phase 7.

---

# 110. PHASE 9 — Payroll Processing and Payslips

Implement:

* payroll runs;
* adjustments;
* review;
* employee release;
* employee acknowledgement;
* disputes;
* finalization;
* payment status;
* payslip generation;
* period locking.

**Dependency:** Phase 8.

---

# 111. PHASE 10 — Dashboards

Implement role-specific dashboards for:

* Team Member;
* Team Leader;
* Operation Manager;
* HR;
* Head HR;
* Admin.

**Dependency:** Phase 9.

---

# 112. PHASE 11 — Reports and Documents

Implement:

* CSV reports;
* attendance report;
* late report;
* leave report;
* overtime report;
* payroll report;
* employee documents;
* private downloads.

**Dependency:** Phase 10.

---

# 113. PHASE 12 — Audit and Basic DevOps

Implement:

* audit viewer;
* application status;
* database health;
* local storage health;
* scheduler status;
* database queue status;
* failed jobs;
* recent errors.

No Redis/Horizon/Pulse/S3.

**Dependency:** Phase 11.

---

# 114. PHASE 13 — Production Hardening

Perform:

* full permission review;
* attendance reconciliation;
* late/grace calculations review;
* overtime tests;
* payroll reconciliation;
* leave workflow tests;
* database index review;
* backup/restore test;
* file-security review;
* responsive testing;
* complete E2E regression.

After this:

```text
HRM V1 COMPLETE
```

---

# 115. Mandatory Attendance Tests

Test:

```text
Shift:
09:00

Grace:
10
```

Cases:

```text
08:59 → Not Late
09:00 → Not Late
09:05 → Not Late
09:09 → Not Late
09:10 → Not Late
09:11 → Late
09:30 → Late
```

Also test:

* grace set to 0;
* grace changed to 20;
* shift-level override;
* temporary shift;
* manually corrected check-in;
* historical grace snapshots;
* duplicate check-in.

---

# 116. Mandatory Leave Tests

Test:

```text
Employee Request
→ TL
→ OM
→ HR
```

Also:

* rejection;
* direct Head HR approval;
* balance update;
* cancellation;
* leave and attendance integration.

---

# 117. Mandatory Overtime Tests

Test:

* weekend attendance detection;
* holiday attendance detection;
* overtime approval;
* one additional daily salary;
* insufficient work duration;
* hourly overtime disabled by default;
* enabling hourly overtime;
* rejected overtime;
* finalized payroll handling.

---

# 118. Mandatory Payroll Tests

Test:

* 1st → month end period;
* 26th → 25th period;
* normal attendance;
* grace-period on-time check-in;
* late check-in;
* late penalty;
* unpaid leave;
* absence;
* overtime earning;
* manual adjustment;
* finalization;
* payslip.

---

# 119. Mandatory E2E Flow — Attendance

```text
Login
  ↓
Check-In Popup
  ↓
Check-In
  ↓
Grace/Late Calculation
  ↓
Dashboard
  ↓
Checkout
```

Example:

```text
Shift:
09:00

Grace:
10

Check-In:
09:09

Expected:
Present / On Time
```

---

# 120. Mandatory E2E Flow — Leave

```text
Employee
→ Leave Request
→ Team Leader
→ Operation Manager
→ HR
→ Approved
```

---

# 121. Mandatory E2E Flow — Overtime

```text
Weekend/Holiday Attendance
→ Overtime Detected
→ Team Leader
→ Operation Manager
→ HR
→ Payroll Earning
```

---

# 122. Mandatory E2E Flow — Holiday

```text
Upcoming Holiday
→ 5-Day Reminder
→ Head HR
→ Approval
→ Notice
→ Announcement
→ Email
```

---

# 123. Mandatory E2E Flow — Payroll

```text
Payroll Period
→ Draft
→ Attendance
→ Late Rules
→ Leave
→ Overtime
→ HR Adjustment
→ Employee Confirmation
→ Finalize
→ Payslip
```

---

# 124. AI Coding Agent Rules

Every coding agent must:

**Process**

1. read this PRD;
2. inspect existing code before writing any — both applications already contain
   scaffolding that must be worked with, not around;
3. implement only the active phase;
4. not start future modules;
5. keep the system deployable after every phase;
6. write tests for all business rules;
7. maintain audit history.

**Architecture**

8. keep Laravel business logic authoritative — the frontend decides nothing (§4);
9. build every HR feature in Next.js, never as an Inertia page (§5.1);
10. touch `backend/resources/js` only for the §79 System Admin console;
11. read `node_modules/next/dist/docs/` before writing Next.js routing or data-fetching
    code — this is Next.js 16 and its conventions differ from earlier majors (§6.1);
12. respect the Mantine/Tailwind boundary in §6.2 — Mantine owns components, Tailwind owns
    page layout;
13. not add Redis, S3, Horizon, Pulse, or any unnecessary infrastructure.

**Configuration, never literals**

14. not hard-code role IDs;
15. not hard-code leave quantities;
16. not hard-code payroll cutoff;
17. not hard-code overtime rate or the overtime full-day threshold;
18. not hard-code late grace minutes into attendance logic;
19. retrieve attendance grace from settings/shift policy via `ShiftService` (§125);
20. not hard-code weekend days — read `weekend_days` (§85).

**Data integrity**

21. never use float or double for money — `DECIMAL(15,4)` in MySQL, `BCMath` in PHP,
    integer minor units or strings over the wire (§141);
22. store timestamps in UTC and convert through the organization timezone for every
    attendance decision (§142);
23. snapshot the rule values used onto each attendance and payroll record so history stays
    reproducible (§22, §95);
24. never recalculate a FINALIZED, PAID, or LOCKED payroll period — issue an arrear (§72).

---

# 125. Important AI Agent Rule — Late Grace

Agents must never write logic such as:

```php
if ($minutesLate > 10)
```

with a hard-coded `10`.

The rule must conceptually be:

```text
grace_minutes
=
resolveGracePeriod(employee, shift, date)

late_threshold
=
shift_start + grace_minutes
```

Then:

```text
check_in <= late_threshold
→ On Time

check_in > late_threshold
→ Late
```

---

# 126. Definition of Done

Every phase must have:

```text
✓ migrations
✓ models
✓ backend business logic
✓ Laravel Policies
✓ validation
✓ APIs
✓ backend tests
✓ frontend integration
✓ frontend loading states
✓ frontend empty states
✓ frontend errors
✓ permission handling
✓ E2E tests
✓ documentation
```

No phase proceeds while critical tests are failing.

---

# 127. AI Agent Phase Prompt Template

```text
You are implementing Phase {N} of the Agency HRM.

Read:

docs/PRD.md                 (authoritative)
docs/architecture.md        (if present — authored in Phase 0)
docs/database.md            (if present)
docs/permissions.md         (if present)
docs/api.md                 (if present)

Do not block if the supplementary docs are absent; the PRD governs.

Inspect all relevant existing source code before making changes.
Both applications contain scaffolding that must be worked with, not around.

Implement ONLY Phase {N}.

Stack:

- Backend: Laravel 13, PHP 8.4, MySQL 9.7, Pest 5, Laravel Boost.
- API auth: Laravel Sanctum Bearer tokens. Fortify supplies the auth flows.
- Frontend: Next.js 16 + React 19 + TypeScript + Mantine (+ Tailwind for layout only).
- Backend and frontend are separate applications.
- backend/resources/js (Inertia) is ONLY the /system DevOps console. Never build
  an HR feature there.
- Read node_modules/next/dist/docs/ before writing Next.js code — this is Next.js 16.

Technical rules:

- All HR business rules belong to Laravel.
- Use Laravel Policies for authorization; scope resolution goes through ScopeResolver.
- Use private local storage.
- Use SMTP.
- Use Laravel Scheduler.
- Use Laravel database queues only where necessary.
- Every status set is a backed PHP enum mirrored by a TypeScript union.

Do NOT add:

- Redis
- S3
- Horizon
- Pulse
- Microservices
- unnecessary infrastructure

Attendance requirements:

- Late grace is configurable.
- Never hard-code grace minutes.
- Global grace is the default.
- Shift-specific grace may override global grace.
- Check-in exactly at grace-end is on time.
- Check-in after grace-end is late.
- Preserve grace used for historical attendance.
- Attribute overnight shifts to the shift's start date (§136).

Payroll requirements:

- Never use floating point for money — DECIMAL(15,4) and BCMath (§141).
- Historical salary must remain reproducible.
- Finalized payroll cannot silently recalculate — issue an arrear (§72, §146).

Before completion report:

1. migrations;
2. models;
3. services;
4. policies;
5. APIs;
6. tests;
7. frontend pages/components;
8. documentation;
9. unresolved issues.

Do not proceed to the next phase.
```

---

# 128. Final Development Sequence

```text
PHASE 0
Foundation
   ↓
PHASE 1
Authentication + RBAC
   ↓
PHASE 2
Employees + Organization + Teams
   ↓
PHASE 3
Settings + Shifts + Late Grace + Calendar
   ↓
PHASE 4
Attendance
   ↓
PHASE 5
Leave
   ↓
PHASE 6
Overtime
   ↓
PHASE 7
Holidays + Notices + Announcements
   ↓
PHASE 8
Payroll Foundation
   ↓
PHASE 9
Payroll Processing + Payslips
   ↓
PHASE 10
Dashboards
   ↓
PHASE 11
Reports + Documents
   ↓
PHASE 12
Audit + Basic DevOps
   ↓
PHASE 13
Production Hardening
   ↓
=========================
       HRM V1 READY
=========================
```

---

# 129. HRM V1 Scope

The complete V1 includes:

```text
Authentication

Roles
Permissions
Scoped Access

Employees
Departments
Teams
Operation Managers
Team Leaders

Shifts
Temporary Shift Overrides

Configurable Late Grace Period

Attendance
Check-In
Checkout
Late Detection
Manual Attendance Correction

Leave
Leave Balances
Leave Approvals

Overtime
Weekend Overtime
Holiday Overtime
Optional Hourly Overtime

Holiday Calendar
Five-Day Holiday Reminder
Holiday Notice

Announcements

Salary Structures

Flexible Payroll Month
1st → Month End
or
Custom 26th → 25th

Attendance Penalties
Late Penalties
Absence Deductions
Unpaid Leave

Overtime Earnings

Payroll Processing
Manual HR Adjustments

Employee Salary Confirmation
Payslips

Role-Based Dashboards

Reports

Documents

Audit Logs

Basic DevOps Dashboard
```

---

# 130. V1 Infrastructure Scope

Use:

```text
Laravel
MySQL
Next.js
React
TypeScript
Mantine
Local Private Storage
SMTP
Cron
Optional Laravel Database Queue
```

Do not require:

```text
Redis
S3
Horizon
Pulse
Kafka
Elasticsearch
Kubernetes
Microservices
```

---

# 131. Future Upgrade Path

When actual requirements justify it:

```text
Database Queue
→ Redis

Local Storage
→ S3

Basic Queue Monitoring
→ Horizon

Basic Logs
→ Advanced Monitoring

Manual Attendance
→ IoT Attendance

Single Office
→ Multi-Office

HRM
→ HR + Agency Resource Management
```

These upgrades must not require rewriting the fundamental HR domains.

---

# 132. Final Attendance Rule

The definitive V1 attendance grace logic is:

```text
SHIFT START
+
CONFIGURED GRACE MINUTES
=
ON-TIME DEADLINE
```

Example:

```text
Shift:
09:00 AM

Grace:
10 minutes

On-Time Deadline:
09:10 AM
```

Therefore:

```text
09:09
→ On Time

09:10
→ On Time

09:11
→ Late
```

If Admin changes:

```text
Grace:
20 minutes
```

then:

```text
On-Time Deadline:
09:20 AM

09:19
→ On Time

09:20
→ On Time

09:21
→ Late
```

This configuration must be controlled from the Admin settings and evaluated exclusively by Laravel.

---

# 133. Final Architecture Principle

The system should remain:

> **Simple enough to deploy and understand today, while preserving clean domain boundaries so it can scale tomorrow.**

V1 should therefore be a **Laravel + MySQL modular monolith API with a separate Next.js frontend**, configurable HR policies, configurable attendance grace periods, configurable payroll periods, day-based overtime with optional hourly overtime, and minimal infrastructure dependencies.

The most important V1 rule is that policy values such as:

```text
10-minute grace
15 casual leaves
25th payroll cutoff
8-hour overtime day
late penalty amount
```

must be **configuration**, not hard-coded application behavior.

---

# 134. Scaffold Validation Report

This section records the reconciliation between PRD v3.0 and the code as it stood on
**2026-08-27**, before any Phase 0 work.

## 134.1 What Actually Existed

Both applications were **untouched starter templates**. A repository-wide search for
`employee`, `attendance`, `payroll`, and `shift` across `app/`, `resources/js/`, `routes/`,
and `database/` returned **zero matches**. `User` was the only model. No HR domain code of
any kind existed.

## 134.2 Mismatches Found and Resolved

| # | v3.0 claimed | Reality on disk | Resolution |
| --- | --- | --- | --- |
| 1 | API-first headless Laravel | `laravel/react-starter-kit` — Inertia SPA with 12 pages | Inertia reduced to the §79 console only (§5.1) |
| 2 | Laravel Sanctum | **Not installed** — `vendor/laravel/` has no sanctum | Phase 0 installs it; Bearer tokens (§92) |
| 3 | MySQL | `DB_CONNECTION=sqlite`, committed `database.sqlite` | Switch to MySQL 9.7 (§101, §140) |
| 4 | `routes/api.php`, `/api/v1` | No api route file; `bootstrap/app.php` registers web + console only | Phase 0 adds both (§5.2) |
| 5 | "Pest or PHPUnit" | Pest 5 installed, 13 existing tests already in Pest style | Standardise on Pest (§5.0) |
| 6 | Mantine UI system | **Not installed** — frontend has 3 deps (next, react, react-dom) | Phase 0 installs Mantine (§6.0) |
| 7 | TanStack Query, Zod, Playwright, Tabler | **None installed** | Phase 0 installs (§6.0) |
| 8 | Mantine as sole UI system | Tailwind v4 present in frontend; shadcn/Radix + Tailwind in backend | Coexistence boundary defined (§6.2) |
| 9 | Root `agency-hrm/` with 5 docs + README | Root is `hrms/`; only `PRD.md` exists | Structure corrected; missing docs are Phase 0 deliverables (§3) |
| 10 | (silent) | `backend/` and `frontend/` are each independent repos (`hrms_backend`, `hrms_frontend`); `hrms/` itself, where docs/ lives, is not a repo | Confirmed as the intended two-repo strategy, not a defect (§3) |
| 11 | (silent) | Fortify `Features::registration()` **enabled** — open self-registration | Must be removed (§92.4) |
| 12 | (silent) | Fortify 2FA installed and unmentioned; `laravel/passkeys` present only transitively | 2FA adopted, passkeys left dormant (§92.5) |
| 13 | (silent) | Frontend is Next.js **16** with a standing agent instruction in `AGENTS.md` | Binding rule added (§6.1) |
| 14 | CSRF protection required | Correct for cookies, wrong for Bearer tokens | Scoped correctly (§93) |
| 15 | (silent) | `MAIL_MAILER=log`, scheduler never registered | Phase 0 tasks (§101) |

## 134.3 Functional Gaps in v3.0

These were specification gaps, not code mismatches — rules the document required but never
defined. Each is now addressed:

| Gap | Where v3.0 broke | Now defined in |
| --- | --- | --- |
| Overnight shifts have no work-date rule, yet §88 shows a 20:00–05:00 night shift | §25 assumes one `work_date` per record | §136 |
| Nothing produces `ABSENT` or `MISSING_CHECKOUT` — no job exists to create them | §29 lists them as statuses | §137 |
| `HALF_DAY` is a status with no producing rule | §29 | §137 |
| Employee `timezone` (§12) contradicts the single org `timezone` (§85) | §94 | §142 |
| No API conventions — envelope, errors, pagination, filtering, idempotency | §86–§91 give 30 endpoints, ~20% of the surface | §139 |
| §28 requires idempotent check-in with no mechanism | §28 | §139.5 |
| "Never use floating point" with no stated representation | §66 | §141 |
| Overtime daily salary — basic or gross? Undefined | §67 | §143 |
| Leave accrual timing, year rollover, mid-year joiners | §37 | §144 |
| Payroll proration for mid-period joiners and leavers — entirely absent | §66 | §145 |
| §72 requires an arrear with no table and no formula | §72 | §146 |
| §70 defines DISPUTED/RESOLVED with no lifecycle or owner | §70 | §147 |
| No first-Admin bootstrap; §13 `INVITED` has no flow | §13 | §148 |

---

# 135. Verified Environment

Confirmed on the development machine, 2026-08-27:

```text
PHP          8.4.23      (Herd)
Node         v26.7.0
MySQL        9.7.1       DBngin, 127.0.0.1:3306, user root, empty password
Laravel      13.17
Next.js      16.3.3
React        19.2.8
```

The `hrms` database does **not** yet exist. Phase 0 creates it:

```sql
CREATE DATABASE hrms       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE hrms_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

MySQL 9.7 is newer than the MySQL 8 most Laravel documentation assumes. Two consequences:

* `utf8mb4_unicode_ci` remains valid, but MySQL 9 defaults to `utf8mb4_0900_ai_ci`. Pin the
  collation explicitly in `config/database.php` so local and production agree;
* verify index-length behaviour on any `VARCHAR(255)` unique key early in Phase 0.

Redis is present in `.env` as a leftover default. It is **not used** and must not be
adopted (§130). Leaving the keys in `.env` is harmless; setting `CACHE_STORE=redis` is not.

---

# 136. Overnight Shifts and Work-Date Attribution

§88 offers a night shift of `20:00 → 05:00`, and §25 stores one `work_date` per attendance
record. v3.0 never said which calendar date that shift belongs to. Without a rule, a
check-in at 04:50 could attach to either day, and every downstream count — late minutes,
absence, overtime, payroll days — becomes ambiguous.

**Rule: an attendance record belongs to the calendar date on which its shift starts.**

```text
Night Shift   20:00 → 05:00
Check-In      2026-08-20 19:58
Check-Out     2026-08-21 05:04

work_date            = 2026-08-20     ← the shift's start date
shift_start_used     = 2026-08-20 20:00
shift_end_used       = 2026-08-21 05:00
worked_minutes       = 546
```

Consequences that must be implemented, not inferred:

* a shift is **overnight** when `end_time <= start_time`; the service adds one day to the
  end when materialising it for a date;
* the check-in matching window opens `N` minutes before `shift_start_used` and closes at
  `shift_end_used + N`, where `N` is configurable (default 240 minutes). A punch outside
  that window does not silently create a second day's record — it is rejected with a clear
  error and offered to HR as a manual correction;
* **weekend and holiday classification uses `work_date`**, not the wall-clock date of the
  punch. A night shift starting Friday 20:00 is a Friday shift even though most of the work
  happens on Saturday. This directly drives weekend overtime (§44) and must be tested;
* the daily close job (§137) processes a date only after that date's latest possible
  `shift_end_used` has passed.

**Early check-in.** v3.0 never addressed check-in *before* shift start. Rule: a check-in at
or before `shift_start_used` is `PRESENT` with `late_minutes = 0`. Early arrival earns no
overtime — overtime comes only from §44/§45/§47, never from arriving early.

---

# 137. Nightly Attendance Close

§29 lists `ABSENT`, `MISSING_CHECKOUT`, and `HALF_DAY` as statuses, but no part of v3.0
creates them. Attendance records are only ever born from a check-in, so an employee who
never checks in generates **no row at all** — and an absence that leaves no record cannot
be counted, reported (§33), or deducted (§66). This job closes that hole.

A scheduled command runs once per day, after the last shift of the target date has ended.

```text
For each active employee, for the target work_date:

  Is it a weekend (§85 weekend_days)?          → WEEKEND       , no deduction
  Is it a holiday (§54)?                       → HOLIDAY       , no deduction
  Is there an approved leave covering it?      → ON_LEAVE      , paid per §36
  Is there a check-in?
        no                                     → ABSENT        , deductible per §66
        yes, no check-out                      → MISSING_CHECKOUT
        yes, both, worked < half-day minutes   → HALF_DAY
        yes, both                              → PRESENT | LATE   (already set at check-in)
```

Rules:

* the job is **idempotent** — re-running it for the same date changes nothing. It never
  overwrites a record carrying `is_manual_adjustment = true`;
* it never processes a date inside a `FINALIZED`, `PAID`, or `LOCKED` payroll period (§64);
* every record it creates snapshots `shift_start_used`, `shift_end_used`, and
  `grace_minutes_used` exactly as a live check-in would (§95);
* `missing_checkout_policy` (§85) selects the treatment: `LEAVE_OPEN` (flag for HR
  correction, no worked minutes credited) or `AUTO_CLOSE_AT_SHIFT_END` (credit up to
  `shift_end_used` and flag). Default `LEAVE_OPEN` — auto-crediting unworked time is a
  payroll decision, and payroll decisions are not made by a cron job;
* `ABSENT` on its own is not "unauthorized absence". Payroll (§66) decides that, using the
  late/absence policy — attendance classifies, payroll charges (§20, §60);
* it emits one summary notification to HR, not one notification per employee.

**Check-in prompt suppression.** §26 opens a check-in popup on login. It must **not** open
when the date resolves to `WEEKEND`, `HOLIDAY`, or `ON_LEAVE`, or when a check-in already
exists. `GET /api/v1/attendance/today` returns the resolved day context so the frontend
never decides this itself (§4).

---

# 138. Half-Day and Leave Interaction

§36 grants leave types half-day support and §29 has a `HALF_DAY` attendance status, but
v3.0 never connected them. Rules:

* a half-day leave request consumes `0.5` from the balance (§37) and marks the matching
  half of the work day as `ON_LEAVE`;
* the employee is still expected to attend the other half. Attendance for that day is
  evaluated against a **half shift**: for a morning half-day leave, the effective shift
  start moves to the shift midpoint, and grace (§16) applies from that adjusted start;
* a day carrying both approved half-day leave and a valid half-day of attendance is
  `HALF_DAY`, is **fully paid**, and produces no absence deduction;
* `attendance_min_minutes_half_day` (§85) defines the attendance threshold. Below it, the
  worked half does not count and the day falls to `ABSENT` for its unworked portion.

---

# 139. API Conventions

v3.0 listed roughly 30 endpoints across §86–§91 — perhaps a fifth of the real surface — and
defined no shared conventions. For an API-first product consumed by a separate frontend,
these conventions are load-bearing.

## 139.1 Envelope

Every successful response:

```json
{
  "data": { },
  "meta": { }
}
```

Collections carry pagination in `meta`:

```json
{
  "data": [],
  "meta": { "current_page": 1, "per_page": 25, "total": 137, "last_page": 6 }
}
```

## 139.2 Errors

One shape for every failure:

```json
{
  "message": "The given data was invalid.",
  "errors": { "late_grace_minutes": ["Must be between 0 and 120."] },
  "code": "VALIDATION_FAILED"
}
```

Status codes:

```text
200  ok
201  created
202  accepted — 2FA challenge required, or queued work
204  no content
401  unauthenticated — token missing, expired, or revoked
403  unauthorized — authenticated but the policy denied
404  not found, and also "exists but out of your scope" (§10)
409  conflict — duplicate check-in, period already finalized
422  validation failed
429  throttled
```

A resource outside the caller's scope returns **404, not 403**. A Team Leader probing
`/employees/{id}` must not learn that an employee exists in another team.

`code` is a stable machine-readable string. The frontend switches on `code`, never on
`message` text.

## 139.3 Listing Conventions

Applies to every collection endpoint:

```text
?page=1&per_page=25            per_page max 100
?sort=-created_at,last_name    leading "-" is descending
?search=nabil                  free-text where the resource supports it
?filter[status]=LATE
?filter[team_id]=4
?filter[date_from]=2026-08-01&filter[date_to]=2026-08-31
?include=employee,shift        explicit relation loading, allow-listed per endpoint
```

Filters are allow-listed per endpoint. Never pass request input into a query builder.

## 139.4 Dates and Money

```text
Timestamps   ISO-8601 with offset      "2026-08-20T09:07:00+06:00"
Dates        "2026-08-20"
Times        "09:00"  (24-hour, no seconds)
Durations    integer minutes
Money        decimal string            "30000.0000"   never a JSON number (§141)
Enums        SCREAMING_SNAKE_CASE, matching the PHP enum exactly
```

## 139.5 Idempotency

§28 requires duplicate-proof check-in and checkout without saying how.

* `POST /attendance/check-in` and `/check-out` accept an optional `Idempotency-Key` header;
* independently, the server enforces the invariant: at most one open check-in per
  `(employee, work_date)`. A second check-in returns **409** with
  `code: "ALREADY_CHECKED_IN"` and the existing record in `data` — the frontend can render
  the correct state from the error alone;
* a unique database index on `(employee_id, work_date)` in `attendance_records` backs this;
  the application check is a courtesy, the index is the guarantee;
* approval endpoints (`/approve`, `/reject`, `/finalize`) are idempotent by state check —
  approving an already-approved record returns 409, never a duplicate approval row.

## 139.6 Complete Endpoint Surface

§86–§91 stay as written. These groups were missing and are required:

```text
auth          POST   /auth/login  /auth/logout  /auth/two-factor-challenge
              POST   /auth/forgot-password  /auth/reset-password
              (accepting an invitation reuses reset-password — see Phase 2 note below)
              GET    /auth/me                    user + roles + permissions + scopes
              PUT    /auth/password
              GET    /auth/sessions              active tokens
              DELETE /auth/sessions/{id}         revoke one device

employees     GET POST /employees
              GET PUT  /employees/{id}
              PATCH    /employees/{id}/status
              GET PUT  /employees/{id}/salary    employee.financial.* gated
              GET      /employees/{id}/documents

org           GET POST PUT DELETE /departments  /teams
              POST     /teams/{id}/members
              DELETE   /teams/{id}/members/{employeeId}
              POST     /employees/{id}/transfer

holidays      GET POST PUT DELETE /holidays
              GET      /holiday-notices
              POST     /holiday-notices/{id}/approve
              GET      /holiday-notices/{id}/download

announcements GET POST /announcements
              POST     /announcements/{id}/publish
              POST     /announcements/{id}/read

leave         GET      /leave-balances
              POST     /leave-balances/{id}/adjust
              POST     /leave-requests/{id}/cancel

payroll       GET      /payroll/entries/{id}/payslip        streams the PDF
              GET      /payroll/disputes
              POST     /payroll/disputes/{id}/resolve
              GET      /payroll/arrears

settings      GET PUT  /settings/organization
              GET PUT  /settings/payroll
              GET PUT  /settings/overtime
              GET PUT  /settings/leave-policies
              GET PUT  /settings/late-penalty-rules

dashboard     GET      /dashboard                role-aware payload (§73–§78)

reports       GET      /reports/{type}           JSON
              POST     /reports/{type}/export    queued CSV, returns a job id
              GET      /reports/exports/{id}     status + download link

notifications GET      /notifications
              POST     /notifications/{id}/read
              POST     /notifications/read-all

files         GET      /documents/{id}/download  authorized private stream (§82)

audit         GET      /audit-logs

system        GET      /system/health            §79 payload, system.health.view
```

## 139.7 Versioning

The prefix is `/api/v1`. Within v1, only additive changes are permitted: new endpoints, new
optional request fields, new response fields. Removing a field, renaming one, changing a
type, or adding a required request field is a **v2** change. The Next.js client and the API
version together, so V1 will not need v2 — but the rule is stated so nobody breaks the
contract casually.

---

# 140. Testing Strategy

**Backend — Pest.** `phpunit.xml` (Pest runs on the PHPUnit test runner under the hood)
currently pins tests to `sqlite` / `:memory:`. That
must change to a dedicated `hrms_test` MySQL database.

The reason is not tidiness. SQLite and MySQL disagree on precisely the things this system
depends on:

```text
DECIMAL          SQLite stores as float — the §141 money guarantee silently fails
Date arithmetic  differing overflow and DST behaviour across midnight (§136)
ENUM/CHECK       constraint violations surface differently or not at all
Locking          SELECT ... FOR UPDATE is a no-op; payroll concurrency is untestable
Collation        case sensitivity differs on unique employee codes and emails
```

Testing money and attendance on SQLite would validate behaviour the production database
does not have. Tests run slower on MySQL; that is the correct trade for a payroll system.

Layers:

```text
Unit         pure calculators — grace resolution, late minutes, daily salary,
             payroll formula, leave balance. No database.
Feature      endpoint + policy + scope. Every endpoint has an authorized case,
             an unauthorized case, and an out-of-scope case (must return 404).
E2E          Playwright, against a seeded database — §119–§123 flows.
```

Coverage floor per phase before it may close:

* every status transition in the phase's enums;
* every permission in the phase, proven to deny as well as allow;
* every worked example written in this PRD, as an assertion — §115's grace table, §63's two
  payroll cutoffs, §67's overtime sum, §96's edge cases;
* one concurrency test per money-moving path (double check-in, double finalize).

**Frontend — Playwright.** Not installed; Phase 0 adds it. Phase 0's smoke test is the app
booting and rendering login. Each later phase adds its §119–§123 flow.

**Seeders.** A deterministic demo seeder is required from Phase 2: one organization, five
departments, ten teams, roughly sixty employees spanning every role and status, ninety days
of attendance including late arrivals, weekend work, and holidays. E2E tests and dashboard
work are not credible against an empty database.

---

# 141. Money Representation

v3.0 said "always use decimal arithmetic" and "never use floating point" without stating a
representation. That is not enough to stop an agent writing `float`.

```text
MySQL        DECIMAL(15,4)          every monetary column, no exceptions
PHP          BCMath, scale 4        never +, -, *, / on money
Casting      Eloquent 'decimal:4'
JSON         string  "30000.0000"   never a JSON number — IEEE-754 loses precision
TypeScript   string, formatted for display only, never parsed to number for arithmetic
Rounding     half-up, at the final step only; intermediate values keep 4 places
Currency     from organization_settings; currency_decimal_places controls display only,
             never storage precision
```

Four decimal places, not two: daily-salary division (§65) and hourly-rate derivation (§48)
both produce repeating values, and rounding those at two places before multiplying by
overtime days visibly drifts.

Every payroll line stores the **inputs** it was computed from, not only the result, so a
disputed payslip (§70) can be recomputed and explained rather than argued about.

---

# 142. Timezone Resolution

v3.0 contains a genuine contradiction: §12 gives each employee a `timezone`, §85 gives the
organization one `timezone`, and §94 says attendance converts to "organization/work
timezone" without saying which wins.

**V1 rule: the organization timezone is authoritative for every attendance and payroll
decision.**

```text
Storage        UTC, always (§94)
Evaluation     organization_settings.timezone
Display        organization timezone
```

`employees.timezone` is retained as a **display-only** preference and an explicit hook for
the multi-office future in §131. It must not appear in any attendance calculation in V1. A
single grace period and a single weekend definition (§85) are organization-wide, so
evaluating attendance per employee timezone would produce results the settings cannot
express.

Concretely: an employee travelling with `Asia/Dubai` on their profile, working a `09:00`
Dhaka shift, is still evaluated against `09:00 Asia/Dhaka`.

Payroll period boundaries (§63, §64) are likewise organization-timezone dates, converted to
UTC at the edges. A period ending `2026-08-25` includes everything through
`2026-08-25 23:59:59` organization time.

DST is not a factor for the initial deployment timezone, but the conversion must go through
a real timezone library — never a fixed offset — so it remains correct if that changes.

---

# 143. Overtime Rate Basis

§67 multiplies "Daily Salary" by approved overtime days without saying which salary. With
the §59 structure (basic + housing + medical + transport + other), basic and gross can
differ by 40%, so this single undefined word changes every overtime payment.

```text
overtime_daily_salary_basis     BASIC | GROSS        default BASIC
```

```text
Daily Salary  =  ( basis amount )  ÷  ( salary days per §65 )
```

The same basis feeds §48's `SALARY_DERIVED_RATE`:

```text
Hourly Rate  =  Daily Salary  ÷  daily working hours
```

where daily working hours comes from the employee's shift `expected_work_minutes` (§15) for
that date — not a global constant, because a temporarily overridden shift (§23) may be
longer or shorter.

The multiplier (§48) applies to hourly overtime only. Day-based weekend and holiday
overtime (§44, §45) pay exactly one daily salary per approved day, with no multiplier —
that is what "1 Additional Daily Salary" means and it must not be quietly scaled.

Every overtime record snapshots the basis, salary days, daily salary, and multiplier used,
so a payment stays reproducible after settings change (§95).

---

# 144. Leave Accrual and Year Boundary

§34 allocates "Casual Leave: 15 days" and §37 gives a balance formula, but v3.0 never said
**when** those days arrive, what happens at year end, or what a mid-year joiner gets.

```text
leave_year_start_month          §85, default 1 (January)
```

**Accrual mode** — per leave type (§36):

```text
UPFRONT     the full annual allocation lands on the leave-year start date
MONTHLY     allocation ÷ 12, credited on the first day of each month
```

Default `UPFRONT`, which matches how §34 reads.

**Mid-year joiners** are prorated from the joining date (§12):

```text
Allocation  =  annual allocation  ×  ( remaining months ÷ 12 )
```

rounded to the nearest 0.5 day. A joiner on 2026-04-15 with a 15-day casual allocation and
a January leave year receives `15 × 9/12 = 11.25 → 11.5` days.

`minimum employment period` (§36) gates **use**, not accrual: the days accrue during
probation and become requestable once the threshold passes.

**Year rollover** runs as a scheduled job on the leave-year start date:

```text
For each employee, for each leave type:

  carry_forward enabled?
        no   →  unused balance expires, logged as a transaction
        yes  →  min(unused, leave_carry_forward_cap_days) carries;
                the remainder expires, logged
  credit the new year's allocation
```

Every movement — accrual, carry-forward, expiry, approval, cancellation, manual adjustment
— writes a row to `leave_balance_transactions`. The balance column is a cached sum, and it
must always be reconstructible by replaying the transactions. A reconciliation test asserts
this for every employee (§114).

**Cancellation** of an approved leave (§39 `CANCELLED`) credits the balance back and
reverses the `ON_LEAVE` attendance for future dates only. Past dates already consumed keep
their records — an employee who did not attend cannot un-take that day by cancelling.

---

# 145. Payroll Proration

v3.0's payroll formula (§66) assumes every employee is present for the whole period. It has
no rule for someone who joins on the 10th or resigns on the 20th — so a mid-period joiner
would be paid a full month's salary.

```text
Payable Days  =  days in period the employee was employed
                 ∩  days in the period

Prorated Base =  Daily Salary  ×  Payable Days
```

Applies whenever:

* `joining_date` falls inside the period (§12);
* employment ends inside the period — `RESIGNED` or `TERMINATED` (§13);
* status is `SUSPENDED` for part of the period, if the suspension is configured as unpaid.

`employee_status_history` (§84) supplies the exact dates, which is why that table exists.

**Mid-period salary change.** §59 requires an effective date and forbids overwriting
history. A salary revision inside a period splits the base into segments:

```text
Base  =  Σ ( segment daily salary  ×  segment days )
```

Each segment appears as its own line on the payslip (§71) so the employee can see why the
figure is not a round month.

Allowances (§59) follow the same proration as base unless a component is explicitly marked
non-prorated.

---

# 146. Overtime Arrears

§72 states that overtime approved after a payroll is finalized becomes an arrear in the
next period — correct, and with no table (§84 now adds `payroll_arrears`) and no mechanics.
Without them the money is simply lost, which is the worst possible failure mode for this
system.

```text
Overtime approved
      ↓
Does its work_date fall in a period that is FINALIZED, PAID, or LOCKED?
      no   →  it is picked up by that period's payroll run normally
      yes  →  create a payroll_arrear
```

An arrear record holds:

```text
employee_id
source_type          OVERTIME | ADJUSTMENT | CORRECTION
source_id
original_period_id   the closed period it belongs to
target_period_id     the open period that will pay it   (null until claimed)
amount               DECIMAL(15,4)
reason
created_by
status               PENDING | APPLIED | CANCELLED
```

The next payroll run claims every `PENDING` arrear for each employee, adds it as its own
payslip line — labelled with the original period, so "Overtime arrear (August 2026)" is
visible on the September payslip — and marks it `APPLIED`.

The same mechanism carries **negative** arrears: an attendance correction (§32) that lands
after finalization and reduces pay. Recovering money is a sensitive act, so a negative
arrear requires `payroll.adjust`, a reason, and an audit entry, and it must be visible to
the employee before the next payroll finalizes.

An arrear is never created against an `OPEN` or `PROCESSING` period — that period simply
recalculates.

---

# 147. Payroll Dispute Lifecycle

§70 gives the employee a `Report an Issue` button and four statuses, but no lifecycle, no
owner, and no effect. Now specified:

```text
Payroll released to employee            entry acknowledgement = PENDING
      ↓
Employee confirms                       ACKNOWLEDGED   — terminal
      ↓
Employee disputes  (reason required)    DISPUTED
      ↓                                 creates a payroll_disputes row
HR investigates                         payroll.dispute.resolve
      ↓
   ├─ upheld    →  adjustment via §68, entry recalculates, RESOLVED,
   │               employee re-acknowledges
   └─ rejected  →  explanation recorded, RESOLVED, employee notified
```

Rules:

* a dispute **blocks finalization of that entry**, not of the whole period. Other employees
  are paid on time; the disputed entry is either resolved or explicitly deferred to the next
  period as an arrear (§146) by someone holding `payroll.finalize`;
* the employee's acknowledgement never changes a number (§70) — only an HR adjustment does;
* the dispute reason, the investigation note, and the resolution are all retained and
  visible to the employee. A dispute resolved without an explanation is not resolved;
* every transition writes an audit entry (§83), with `PAYROLL_DISPUTE_RAISED` and
  `PAYROLL_DISPUTE_RESOLVED` added to that event list;
* a dispute window is configurable and defaults to 7 days from release. After it closes,
  entries still `PENDING` are treated as acknowledged, logged as auto-acknowledged, and the
  period may finalize. Without this, one unresponsive employee stalls payroll indefinitely.

---

# 148. Open Decisions

These require a business answer before the phase that needs them. Each is listed with a
recommendation so no phase blocks waiting for a meeting.

| # | Decision | Needed by | Recommendation |
| --- | --- | --- | --- |
| 1 | **First Admin bootstrap.** With registration closed (§92.4), how does the first Admin exist? | Phase 1 | A one-time `hrm:install` artisan command creating the Admin, the role/permission set, and organization settings. Refuses to run if any user exists. |
| 2 | **Invitation flow.** §13 has `INVITED` with no mechanism. | Phase 2 | HR creates the employee; Laravel emails a signed URL expiring in 72h; accepting sets the password and moves the status to `ACTIVE` or `PROBATION`. Re-sendable, revocable. |
| 3 | **§41 "Admin → Authorized Admin".** Undefined — which Admin approves an Admin's leave? | Phase 5 | A nominated `leave_approver_user_id` on the employee record, defaulting to another Admin. An Admin may never approve their own leave. |
| 4 | **§38 "Management Approved".** Named as a concept but absent from the §39 status list. | Phase 5 | Not a status — a derived flag, true once TL and OM have both approved. Adding it as a status would double the state machine. |
| 5 | **Weekend definition scope.** `weekend_days` is organization-wide; agencies often run client-aligned teams on different weekends. | Phase 3 | Organization-wide for V1 as written. Note it as the most likely first extension, and keep the resolver signature `(employee, date)` so a team-level override slots in without touching callers. |
| 6 | **Late-penalty counting window.** §61 counts "5 late days" — within what period? | Phase 8 | The payroll period (§64). Count resets each period. State it on the settings screen so HR is not surprised. |
| 7 | **Overtime below the threshold.** §46 sets a full overtime day at 8h with half-day OFF; what does 6h of weekend work earn? | Phase 6 | Zero overtime, but the attendance record still shows the worked minutes, and HR may grant it manually via §68. Surface it in the overtime list as `DETECTED` → `REJECTED (insufficient duration)` rather than hiding it. |
| 8 | **Payslip PDF engine.** §71 requires a stored PDF; no library is chosen. | Phase 9 | `barryvdh/laravel-dompdf` — pure PHP, no binary dependency, adequate for a payslip. Generate through the queue (§81). |
| 9 | **Attendance data retention.** Not addressed anywhere. | Phase 12 | Retain attendance and payroll indefinitely for V1; archive rather than delete. Add the policy before the first employee is terminated. |
| 10 | **Statutory deductions.** §66 has no tax or provident fund. | — | Confirmed out of V1 scope (§93). The `payroll_entry_lines` table (§84) makes adding them later additive rather than structural. |

---

# 149. Change Log — v3.0 → v4.0

```text
CORRECTED
  Backend is the Laravel React starter kit, not a bare API skeleton  §5.0, §5.1
  Sanctum is not installed; auth becomes Bearer tokens              §92
  Database is sqlite in .env and phpunit.xml; must become MySQL     §101, §140
  No routes/api.php and no api: registration                        §5.2
  Pest 5 + Laravel Boost are installed, not PHPUnit-only               §5.0
  Mantine, TanStack Query, Zod, Playwright are all uninstalled      §6.0
  Mantine now coexists with Tailwind v4 under an explicit boundary  §6.2
  Next.js 16 agent constraint                                       §6.1
  Repository root is hrms/, four referenced docs do not exist       §3
  CSRF requirement scoped correctly for token auth                  §93
  Open self-registration must be disabled                           §92.4
  2FA and passkeys adopted                                          §92.5

ADDED — specification gaps
  DEPARTMENT scope                                                  §10
  10 missing permissions incl. financial and report separation      §11
  9 missing tables incl. payroll_arrears and payroll_disputes       §84
  16 missing organization settings                                  §85
  Overnight shift work-date attribution, early check-in             §136
  Nightly attendance close producing ABSENT/MISSING_CHECKOUT        §137
  Half-day and leave interaction                                    §138
  API conventions, error shape, idempotency, full endpoint surface  §139
  Testing strategy and the MySQL rationale                          §140
  Money representation                                              §141
  Timezone conflict resolved                                        §142
  Overtime rate basis                                               §143
  Leave accrual, proration, year rollover                           §144
  Payroll proration for joiners, leavers, mid-period raises         §145
  Overtime arrear mechanics                                         §146
  Payroll dispute lifecycle                                         §147
  10 open decisions with recommendations                            §148

UNCHANGED
  Every HR business rule in §7–§83 that the code did not contradict.
  The grace-period model (§16–§22, §30, §31, §62, §95–§98, §125, §132)
  is the strongest part of v3.0 and is carried through intact.
```
