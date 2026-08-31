## Purpose

Gives operators an answer to "did the scheduled jobs actually run, and did they
succeed?" by recording the outcome of every scheduled-command execution, not just
a single last-seen heartbeat.

## ADDED Requirements

### Requirement: Every scheduled-command run is recorded

The system SHALL persist one record for each execution of a scheduled console
command, capturing the command, the start time, the finish time, the duration,
the resulting exit code, and a bounded tail of the command's output. A run that
throws SHALL be recorded as failed with the error message. A run the scheduler
skips (because its `when`/`skip` conditions or overlap guard prevented it) SHALL
be recorded as skipped.

#### Scenario: Successful run

- **WHEN** a scheduled command completes with exit code 0
- **THEN** a run record exists with status succeeded, the start and finish times, the measured duration, and exit code 0

#### Scenario: Failing run

- **WHEN** a scheduled command exits non-zero or throws
- **THEN** a run record exists with status failed, the exit code (or error message), and the captured output tail

#### Scenario: Skipped run

- **WHEN** the scheduler skips a due command because a previous invocation is still running
- **THEN** a run record exists with status skipped

#### Scenario: Output is bounded

- **WHEN** a scheduled command produces a very large amount of output
- **THEN** only a bounded tail of that output is stored on the run record

### Requirement: Run history is retained and prunable

Run records SHALL be retained for at least 30 days and SHALL be automatically
pruned beyond a configured retention window so the table cannot grow without
bound.

#### Scenario: Old runs are pruned

- **WHEN** the retention window is 30 days and a run is older than that
- **THEN** the pruning process removes it

### Requirement: Schedule page shows each command's state

The Schedule page SHALL list every command registered in the application
scheduler with its cron expression, its next due time, and — from the run history
— its most recent run (status, start time, duration) and its count of failures in
a recent window. Selecting a command SHALL reveal its recent run history with the
per-run detail, including the output tail.

#### Scenario: Command that has run

- **WHEN** an authorized user opens the Schedule page and a listed command has at least one recorded run
- **THEN** that command's row shows its cron expression, next due time, and the status, time, and duration of its latest run

#### Scenario: Command that has never run

- **WHEN** a registered command has no recorded run
- **THEN** its row still lists the command and cron expression and marks the last run as none

#### Scenario: Inspecting run history

- **WHEN** the user selects a command on the Schedule page
- **THEN** its recent runs are listed newest-first with start time, duration, status, and output tail

### Requirement: Heartbeat liveness signal is preserved

The existing every-minute scheduler heartbeat SHALL continue to be written and
SHALL remain the signal used by the health overview to report scheduler
freshness. Run tracking SHALL NOT replace it.

#### Scenario: Heartbeat still drives freshness

- **WHEN** the scheduler has run within the freshness threshold
- **THEN** the Overview page reports the scheduler as ok regardless of individual command outcomes
