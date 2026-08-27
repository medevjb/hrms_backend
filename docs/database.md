# Database

Quick-reference companion to `docs/PRD.md` §84–§85. If anything here and the PRD
disagree, the PRD wins.

## Connections

```text
Dev / prod   MySQL 9, database `hrms`
Tests        MySQL 9, database `hrms_test` — RefreshDatabase per test (see backend
             tests/Pest.php); never SQLite (PRD §140 explains why: DECIMAL, locking,
             and date-arithmetic semantics differ from production)
Collation    utf8mb4_unicode_ci, pinned explicitly — MySQL 9's own default
             (utf8mb4_0900_ai_ci) is not what this schema was designed against
```

## Framework tables (already migrated, do not recreate)

```text
users, password_reset_tokens, sessions, cache, cache_locks,
jobs, job_batches, failed_jobs, personal_access_tokens (Sanctum)
```

## HR domain tables (PRD §84 — build these across the phases that need them)

```text
roles, permissions, role_permissions, user_roles            ← user_roles carries scope

employees, employee_status_history

departments, teams, team_members                            ← keep from/to dates

organization_settings

shifts, employee_shifts, shift_overrides

attendance_events, attendance_records, attendance_adjustments

leave_types, leave_policies, leave_balances,
leave_balance_transactions, leave_requests, leave_approvals

overtime_settings, overtime_records, overtime_approvals

holidays, holiday_reminders, holiday_notices

announcements, announcement_targets, announcement_reads

salary_structures, salary_components, employee_salaries

payroll_settings, payroll_periods, payroll_runs, payroll_entries,
payroll_entry_lines, payroll_adjustments, payroll_disputes, payroll_arrears

payslips

notifications, documents, audit_logs
```

Every table added over the original spec (`employee_status_history`,
`holiday_reminders`, `holiday_notices`, `announcement_reads`, `payroll_entry_lines`,
`payroll_disputes`, `payroll_arrears`) closes a specific gap explained in PRD §84's
mismatch table — read that before assuming a table is unnecessary.

## Rules that apply to every table

* **Money is `DECIMAL(15,4)`.** Never `FLOAT`/`DOUBLE`. Four decimal places, not two —
  daily-salary division and hourly-rate derivation both produce repeating values that
  drift if rounded early (PRD §141).
* **Timestamps are UTC.** Attendance and payroll evaluation converts to
  `organization_settings.timezone` — never to an employee's own `timezone`, which is
  display-only (PRD §142).
* **Snapshot the rule, not just the result.** `attendance_records.grace_minutes_used`,
  `shift_start_used`, `shift_end_used` and equivalent payroll snapshot columns exist so
  changing a setting today never rewrites yesterday's history (PRD §22, §95).
* **Audit rows are append-only.** No endpoint updates or deletes an `audit_logs` row.
* **A FINALIZED/PAID/LOCKED payroll period is immutable.** Corrections after that point
  go through `payroll_arrears`, never a retroactive `UPDATE` (PRD §72, §146).

## Organization settings (PRD §85)

Read through a settings service at evaluation time — never hard-coded:

```text
company_name, company_logo_path, timezone, currency, currency_decimal_places
late_grace_minutes, weekend_days, default_shift_id
payroll_cutoff_day, salary_day_calculation_method
overtime_enabled, weekend_overtime_enabled, holiday_overtime_enabled,
hourly_overtime_enabled, overtime_full_day_minutes, overtime_daily_salary_basis,
overtime_hourly_rate_mode, overtime_hourly_fixed_rate, overtime_hourly_multiplier
auto_absent_enabled, missing_checkout_policy, attendance_min_minutes_half_day
leave_year_start_month, leave_carry_forward_cap_days
```

Complex rules (late-penalty thresholds, leave policies) are their own tables with
effective dates and history — not settings keys. Don't put a business rule in a JSON
blob because it's convenient; give it a table once it has more than one field.
