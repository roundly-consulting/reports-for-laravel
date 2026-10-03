# Changelog

All notable changes to `reports-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Let any model file reports against any reportable model with the `GivesReports` and
  `HasReports` traits, or anonymously as a guest via `asGuest()`.
- A fluent `Reports` facade (`Reports::report($post)->by($user)->for(...)->because(...)->create()`)
  backed by Action classes and DTOs.
- Typed, validated reasons through the `Reason` enum, plus your own custom reason slugs from config.
- A guarded status lifecycle (`Pending`, `InReview`, `Resolved`, `Rejected`, `Closed`) with
  `Reports::changeStatus()`, `review()`, `close()` (the `ChangeReportStatusAction`; the model's
  `changeStatusTo()` goes through it), and `Reports::resolve()` / `reject()` recording who
  decided, when and why.
- `Reports::create(CreateReportData)` for filing a report from a DTO in one call.
- Multi-moderator sign-off with `Reports::moderate()`: unanimous, quorum, any or weighted rules
  built on `approvals-for-laravel`.
- Duplicate-report prevention per reporter or guest, scoped to open reports or all reports.
- Aggregation helpers and query scopes: `reportsCount()`, `isReportedBy()`, `withReportCounts()`,
  `mostReported()` and `reportedMoreThan()`.
- A `ReportThresholdReached` event when a subject crosses a configurable report count.
- Lifecycle events: `ReportCreated`, `ReportStatusChanged`, `ReportResolved` and `ReportRejected`.
- `Reports::prune(?days, force:)` (the `PruneReportsAction`) and the `reports:prune` command that
  wraps it; `reports:recount` to list per-subject open counts.
- `Reports::reasons()` (slug => label), `reasonLabel()`, `defaultReason()` and `allowsReason()`.
- `Reports::fake()`: a `ReportsManager` subtype installed behind the facade and the container
  that records every report, moderation, resolve, reject, status change and prune — through the
  facade, an injected manager, the builders, the commands, `changeStatusTo()` or the
  `GivesReports` trait — with `assert*()` and `assertNothing*()` for each.
- Soft-deletable reports and `bigint` / `uuid` / `ulid` morph keys via the `key_type` config.

### Changed

- The manager moved to `RoundlyConsulting\Reports\ReportsManager` (was `Support\ReportsManager`),
  is no longer `final` and resolves every action from the container.
- `Reports::reasons()` returns `slug => label` instead of a list of slugs.
- `PendingReport` and `PendingModeration` take the manager; moderation opens through the new
  `OpenModerationAction`.
