## Purpose

Lets a DevOps engineer read and search the application log from the console
during an incident, instead of needing shell access to the server's log files.

## ADDED Requirements

### Requirement: Log entries are read as structured records

The system SHALL parse the application log file into discrete entries, each
exposing its timestamp, level, channel, message, and — when present — the
associated exception class and stack trace. Multi-line entries (a message
followed by a stack trace) SHALL be treated as one entry. Lines that cannot be
parsed SHALL be surfaced as raw entries rather than dropped.

#### Scenario: Structured entry

- **WHEN** the log contains an `ERROR` line with a following stack trace
- **THEN** the reader returns one entry with level ERROR, the message, and the stack trace attached

#### Scenario: Unparseable line

- **WHEN** a log line does not match the expected format
- **THEN** it is returned as a raw entry, not discarded

### Requirement: Log viewer filtering and pagination

The Logs page SHALL let an authorized user filter entries by minimum level, by a
time range, and by a full-text search over the message and exception text, and
SHALL return results newest-first in pages of a bounded size. Filters SHALL be
combinable.

#### Scenario: Filter by level

- **WHEN** the user selects minimum level `WARNING`
- **THEN** only entries at WARNING or higher are listed

#### Scenario: Combined filter and search

- **WHEN** the user searches for a term and restricts the time range
- **THEN** only entries within that range whose message or exception text contains the term are listed

#### Scenario: Pagination

- **WHEN** more entries match than fit in one page
- **THEN** the results are paginated and older matches are reachable through pagination

### Requirement: Stack traces are expandable

An entry that carries a stack trace SHALL be listed in a collapsed form showing
the message and level, and SHALL expand on demand to show the full trace.

#### Scenario: Expanding a trace

- **WHEN** the user expands a log entry that has a stack trace
- **THEN** the full stack trace is shown

### Requirement: Top errors summary

The Logs page SHALL present a summary of error-or-higher entries over a recent
window, grouped by a normalized form of the message, showing each group's
occurrence count, its level, and the time it was last seen, ordered by count
descending.

#### Scenario: Recurring error is grouped

- **WHEN** the same error message appears many times in the window
- **THEN** the summary shows it once with the total count and the most recent occurrence time

#### Scenario: 24h error count feeds the overview

- **WHEN** the health overview requests the 24-hour error count
- **THEN** it receives the number of error-or-higher entries logged in the last 24 hours

### Requirement: Log reading is resilient to log volume

Reading and filtering the log SHALL not require loading the entire file into
memory at once, and SHALL complete within a bounded time for a log file at the
configured rotation size. If the log file is absent, the viewer SHALL report an
empty result rather than error.

#### Scenario: Missing log file

- **WHEN** the log file does not exist
- **THEN** the Logs page renders with no entries and no error

#### Scenario: Large log file

- **WHEN** the log file is at its maximum rotated size
- **THEN** a page of results is returned within the bounded response time
