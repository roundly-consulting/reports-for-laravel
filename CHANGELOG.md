# Changelog

All notable changes to `reports-for-laravel` will be documented in this file.

## Unreleased

Initial moderation-foundation feature set (pre-1.0; no prior tagged release, so these are
the initial public API rather than breaking changes):

### Added

- Fluent `Reports` facade and `PendingReport` builder backed by an Action layer and DTOs.
- Config-driven schema: `table`, `morph_key_type` (`bigint`/`uuid`), reasons, duplicate
  prevention, strict transitions, threshold, and prune defaults.
- Typed, translatable, host-extensible report reasons (`Reason` enum + `ReasonRegistry` +
  native JSON-free `resources/lang` labels).
- Guarded status lifecycle (`Pending`, `InReview`, `Resolved`, `Rejected`, `Closed`) with a
  transition table, resolution metadata, and `ReportResolved` / `ReportRejected` events.
- Duplicate-report prevention (open/any scope) and guest / anonymous reporting.
- Aggregation helpers and scopes (`reportsCount`, `hasBeenReported`, `isReportedBy`,
  `withReportCounts`, `mostReported`, `reportedMoreThan`, `pendingReports`).
- Threshold auto-action via the `ReportThresholdReached` event.
- Package exceptions: `ReportsException`, `InvalidStatusTransitionException`,
  `DuplicateReportException`, `UnknownReportReasonException`.
- Artisan commands `reports:prune` (soft-delete by default, `--force` to hard-delete) and
  `reports:recount`.

### Changed

- Renamed the report `type` column to `reason`.
- Renamed `Status::New`/`Status::Solving` to `Status::Pending`/`Status::InReview` and added
  `Resolved` / `Rejected`. Status backed values are now lowercase.
- The reporter morph is now nullable (supports guests).
- `changeStatusTo()` now guards transitions when `strict_transitions` is enabled.
