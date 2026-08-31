## Purpose

Shows whether background work is flowing or backing up, and gives the operator
the two actions an incident needs: retry a failed job, or drop it.

## ADDED Requirements

### Requirement: Queue depth and age are reported

The Queue page SHALL report, for the database queue, the number of pending jobs
in total and broken down by queue name, and the age of the oldest pending job.
When the configured queue connection is not the database, the page SHALL state
that depth details are unavailable for that connection rather than showing
misleading zeros.

#### Scenario: Backlog present

- **WHEN** pending jobs exist on the database queue
- **THEN** the page shows the total count, the per-queue breakdown, and the oldest job's age

#### Scenario: Non-database connection

- **WHEN** the default queue connection is not `database`
- **THEN** the page reports that queue-depth detail is unavailable for that connection

### Requirement: Failed jobs are listed with detail

The Queue page SHALL list failed jobs newest-first, each showing its uuid,
connection, queue, the failure time, and a short exception summary. Selecting an
entry SHALL reveal the full stored exception. The list SHALL be paginated.

#### Scenario: Inspecting a failure

- **WHEN** an authorized user expands a failed-job entry
- **THEN** the full exception text stored for that job is shown

### Requirement: Retry and forget actions

The Queue page SHALL let an authorized user retry a single failed job by its
uuid, retry all failed jobs, and permanently forget a single failed job. Each
action SHALL require the `system.health.view` permission, SHALL be submitted as a
state-changing request protected against CSRF, and SHALL report success or
failure back to the operator. Retrying SHALL re-dispatch the job and remove it
from the failed list; forgetting SHALL remove it with no re-dispatch.

#### Scenario: Retry one

- **WHEN** the operator retries a failed job by uuid
- **THEN** that job is re-dispatched onto its queue
- **AND** it no longer appears in the failed-jobs list

#### Scenario: Retry all

- **WHEN** the operator chooses retry-all with several failed jobs present
- **THEN** every failed job is re-dispatched
- **AND** the failed-jobs list becomes empty

#### Scenario: Forget one

- **WHEN** the operator forgets a failed job by uuid
- **THEN** that job is removed from the failed-jobs list
- **AND** it is not re-dispatched

#### Scenario: Unknown uuid

- **WHEN** the operator retries or forgets a uuid that is not in the failed list
- **THEN** the action reports a not-found failure and changes nothing

### Requirement: Queue actions are audited

Retrying or forgetting a failed job SHALL write an audit-log entry recording the
actor, the action, and the affected job uuid.

#### Scenario: Forget is recorded

- **WHEN** an operator forgets a failed job
- **THEN** an audit entry records who forgot which job uuid and when
