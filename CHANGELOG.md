# Changelog

All notable changes to `reports-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.1.0 - 2026-10-06

### Added

- `reports.primary_key_type` (env `REPORTS_PRIMARY_KEY_TYPE`): `bigint` (the default), `uuid` or
  `ulid` for the reports table's own id, honoured by the migration and the `Report` model. Set it
  to match `approvals.key_type`: with UUID-keyed moderators on PostgreSQL, moderation could not
  work before, because approvals stores the report id in a uuid column. It applies to new
  installs; the default keeps the schema exactly as it was. Converting an existing install is a
  data migration, described in the docs.

### Changed

- Requires `roundly-consulting/approvals-for-laravel` `^1.0.1`: UUID moderation on PostgreSQL
  needs its `0007` migration.
- Maintenance: `composer.json` `homepage` and `support.docs` point at the documentation website.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist.

### Fixed

- A model can now use both `GivesReports` and `HasReports` (users reporting users). Together they
  were a fatal trait collision.
- `Reports::fake()` assertions (`assertReported`, `assertModerated`, `assertResolved`,
  `assertRejected`, `assertStatusChanged`) no longer pass for a different unsaved report or model.
  The fake's reports are unsaved, and any two of them used to match.
- `Reports::fake()` now refuses what the real manager refuses, with the same exception, and records
  nothing: an unknown reason, a duplicate report, moderation without moderators, a prune without a
  window (so `reports:prune` fails under the fake too), and a status move `strict_transitions`
  forbids. It used to record all of these, so a test could pass over a call that fails in
  production.
- `Reports::moderate()->open()` now throws the new `ModerationNotAllowedException` for a settled
  (resolved, rejected or closed) report, or one that already has a pending moderation request. Both
  used to open a request that left the report stuck: a settled report silently ignored the
  moderators' outcome, and a second request kept the report under moderation after the first one
  had decided it.
- `Report::factory()` (and a subclass's `factory()`) now builds the model configured in
  `reports.model`. It always built the packaged `Report`, so a host model's casts and events never
  ran for seeded reports.
- Filing a report against an unsaved subject, or by an unsaved reporter, now throws a
  `LogicException` and writes nothing. It used to store a report with a NULL id that no relation
  finds, and a second such report by the same user on another unsaved model of the class was
  refused as a duplicate.
- `reports:recount --threshold` now refuses a value that is not a whole number (`abc`, `-3`, `2.9`)
  and exits with an error, like `reports:prune --days`. It used to list every subject for junk or
  negative values and truncate decimals.

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
