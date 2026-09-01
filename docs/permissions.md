# Permissions

Quick-reference companion to `docs/PRD.md` §8–§11. If anything here and the PRD
disagree, the PRD wins.

## The model

```text
Role + Permission + Scope = Access
```

A permission alone means nothing — `attendance.view` without a scope doesn't say whose
attendance. Every grant is `permission @ scope`. Never hard-code authorization by role ID
(PRD §11) — check the permission, resolve the scope, let `ScopeResolver` turn that into
an employee-ID set (PRD §10).

## Roles (PRD §8)

```text
1. Admin
2. Head of HR
3. HR
4. Operation Manager
5. Team Leader
6. Team Member
7. System Admin / DevOps   (optional technical role, /system console only)
```

## The baseline grant every employee gets (PRD §8)

Inviting an employee (`EmployeeService::invite`) assigns the **Team Member** role at
**SELF** scope to their paired user, so self-service works from day one: request leave,
view own attendance, read holidays and announcements, see own payslips
(`leave.request`, `attendance.view`, `holiday.view`, `announcement.view`,
`payslip.view_self`). Higher roles (Team Leader, HR, …) layer on top through the Roles
module — they never replace this one. A user with **no** role assignment sees an empty
sidebar and a 403 from every scoped endpoint.

## Two separate chains (PRD §9)

```text
Operation chain          HR chain
Admin                     Admin
  ↓                         ↓
Operation Manager         Head of HR
  ↓                         ↓
Team Leader                HR
  ↓                         ↓
Team Member               Team Leader / Team Member
```

HR authority and operational reporting authority are different concepts — a Team
Leader's Operation Manager is not automatically their HR approver.

## Scopes (PRD §10 — the complete V1 set)

```text
SELF             the acting user's own records only
TEAM             every member of teams the user leads
DEPARTMENT       every team inside departments the user manages
OPERATION        every employee under the user's operational chain
HR_SCOPE         every employee the user is HR-responsible for
ALL_EMPLOYEES    the entire workforce
SYSTEM           technical/infrastructure resources, no employee data
```

## Permissions (PRD §11 — the complete V1 set)

```text
employee.view / .create / .update / .archive
employee.financial.view / .financial.manage      ← stronger than employee.view (PRD §12)

department.view / .manage
team.view / .manage

shift.view / .manage / .override

attendance.view / .manage / .correct

leave.request / .review / .approve / .override / .policy.manage / .balance.adjust

overtime.view / .review / .approve / .adjust / .policy.manage

holiday.view / .manage / .notice.approve

announcement.view / .create / .publish

payroll.view / .prepare / .adjust / .finalize / .dispute.resolve

payslip.view_self / .view_all

report.view / .export

document.view / .manage

settings.manage / payroll.settings.manage / attendance.settings.manage

audit.view

system.health.view
```

## Where enforcement lives

```text
Backend    Laravel Policies check permission + resolve scope. This is the only
           real authorization boundary (PRD §4, §93).
Frontend   Hides/shows controls for UX only. Never treat a hidden button as
           security (PRD §92.1). A frontend permission check that isn't
           backed by a Policy check is a bug, not a shortcut.
```

An out-of-scope resource returns **404, not 403** (PRD §139.2) — a Team Leader probing
another team's employee ID must not learn that the record exists.

## 2FA requirement (PRD §92.5)

Mandatory for any role holding `payroll.finalize`, `employee.financial.manage`, or
`settings.manage`. Optional for everyone else.
