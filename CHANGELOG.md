# Changelog

All notable changes to `reports-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Let any model file reports against any reportable model with the `GivesReports` and
  `HasReports` traits, or anonymously as a guest via `asGuest()`.
- A fluent `Reports` facade (`Reports::report($post)->by($user)->for(...)->because(...)->create()`)
  backed by Action classes and DTOs.
- Typed, validated reasons through the `Reason` enum, plus your own custom reason slugs from config.
- A guarded status lifecycle (`Pending`, `InReview`, `Resolved`, `Rejected`, `Closed`) with
  `changeStatusTo()`, `Reports::resolve()` and `Reports::reject()` recording who decided, when
  and why.
- Multi-moderator sign-off with `Reports::moderate()`: unanimous, quorum, any or weighted rules
  built on `approvals-for-laravel`.
- Duplicate-report prevention per reporter or guest, scoped to open reports or all reports.
- Aggregation helpers and query scopes: `reportsCount()`, `isReportedBy()`, `withReportCounts()`,
  `mostReported()` and `reportedMoreThan()`.
- A `ReportThresholdReached` event when a subject crosses a configurable report count.
- Lifecycle events: `ReportCreated`, `ReportStatusChanged`, `ReportResolved` and `ReportRejected`.
- `reports:prune` and `reports:recount` Artisan commands.
- Soft-deletable reports and `bigint` / `uuid` / `ulid` morph keys via the `key_type` config.
