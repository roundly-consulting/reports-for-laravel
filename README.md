<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/reports-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reports-for-laravel">
    <img src="art/hero.png" alt="Reports for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Reports for Laravel

A content-moderation and flagging foundation for Laravel. Let any model **file** reports
(e.g. a `User`, or anonymous guests) against any **reportable** model (e.g. a `Post`), tag
each report with a typed reason, move it through a guarded status lifecycle, prevent
duplicate spam, surface aggregation insights, **route resolution through multi-moderator
sign-off**, and react to threshold crossings and lifecycle changes via events — with a fluent
facade, Action classes, and DTOs underneath.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Integrates with

Reports builds on two of our own packages (installed automatically as dependencies):

- [`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel) —
  a `Report` is an approvals **subject**, so resolving/rejecting a report can require N
  moderators to agree (unanimous / quorum / any / weighted) before it changes status. See
  **Moderation** below.
- [`enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel) — the
  `Status` and `Reason` enums adopt its `Helpers` trait
  (`values()`/`labels()`/`options()`/`toOptions()`/`validationRule()`/`readable()`/…).

## Installation

Install the package via Composer:

```bash
composer require roundly-consulting/reports-for-laravel
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag="reports-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="reports-config"
```

## Configuration

The published config file (`config/reports.php`):

```php
return [
    'model' => RoundlyConsulting\Reports\Models\Report::class,
    'table' => 'reports',
    'morph_key_type' => 'bigint',
    'default_reason' => 'other',
    'reasons' => ['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other'],
    'allow_unknown_reasons' => false,
    'prevent_duplicates' => true,
    'duplicate_scope' => 'open',
    'strict_transitions' => true,
    'threshold' => null,
    'prune_after_days' => null,
    'moderation' => [
        'default_rule' => 'unanimous',
        'default_quorum' => null,
    ],
];
```

The `moderation` block seeds the `Reports::moderate()` builder. `default_rule` is an
`ApprovalRule` value (`unanimous`, `quorum`, `any`, `weighted`); `default_quorum` is the
approval count used by the `quorum` rule (`null` = require every declared moderator).

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `Report::class` | The Eloquent model used to store reports. Point at your own subclass to customise. |
| `table` | `string` | `'reports'` | The database table reports are stored in. |
| `morph_key_type` | `'bigint'\|'uuid'` | `'bigint'` | Key type for the polymorphic reporter / reported / resolved_by columns. Use `uuid` for UUID-keyed models. |
| `default_reason` | `string` | `'other'` | The reason used when a report is filed without one. |
| `reasons` | `list<string>` | enum values | The allowed reason slugs. Add your own custom slugs here. |
| `allow_unknown_reasons` | `bool` | `false` | When `true`, any reason slug is accepted (no validation). |
| `prevent_duplicates` | `bool` | `true` | Prevent the same reporter / guest from reporting the same subject twice. |
| `duplicate_scope` | `'open'\|'any'` | `'open'` | `open` dedupes only against non-terminal reports; `any` against every report ever filed. |
| `strict_transitions` | `bool` | `true` | When `true`, only declared status transitions are allowed; illegal moves throw. |
| `threshold` | `int\|null` | `null` | When set, a `ReportThresholdReached` event fires once when a subject's open report count reaches this number. `null` disables it. |
| `prune_after_days` | `int\|null` | `null` | Default age (days) for the `reports:prune` command when no `--days` is given. |

The package works with zero published configuration — these defaults are merged in
automatically.

> **UUID hosts:** when `morph_key_type` is `uuid`, your reporter, reportable, and resolver
> models must use string/UUID primary keys.

## Usage

### Preparing your models

Add the `GivesReports` trait to models that file reports, and the `HasReports` trait to
models that can be reported:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Reports\Contracts\Reporter;
use RoundlyConsulting\Reports\Traits\GivesReports;
use RoundlyConsulting\Reports\Traits\HasReports;

class User extends Model implements Reporter
{
    use GivesReports;
}

class Post extends Model implements Reportable
{
    use HasReports;
}
```

### Filing a report (fluent facade — recommended)

```php
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Facades\Reports;

$report = Reports::report($post)
    ->by($user)
    ->for(Reason::Abuse)            // typed, validated reason
    ->because('This post violates the community guidelines.')
    ->create();
```

You can also start from the reporter, or use a custom reason slug:

```php
Reports::from($user)->about($post)->for('copyright')->create();
```

### Guest / anonymous reports

```php
Reports::report($post)
    ->asGuest(hash('sha256', $request->ip()))
    ->for('spam')
    ->because('Obvious spam.')
    ->create();
```

### Filing a report (trait)

```php
// Defaults to the configured default reason.
$report = $user->giveReportTo($post, 'Violates the guidelines.', Reason::Abuse);

// Or start a fluent build from the reporter:
$user->report($post)->for(Reason::Spam)->because('Spammy.')->create();
```

### Reasons

Reports are tagged with a reason slug validated against `config('reports.reasons')`. Filing
a report with a disallowed slug throws `UnknownReportReasonException` (unless
`allow_unknown_reasons` is `true`). Default reasons map to the `Reason` enum, which adopts
the `enums-for-laravel` `Helpers` trait:

```php
use RoundlyConsulting\Reports\Enums\Reason;

Reason::Abuse->label();      // "Abuse"
Reason::Inappropriate->readable(); // "Inappropriate"
Reason::values();            // ['spam', 'abuse', 'harassment', ...]
Reason::validationRule();    // "in:spam,abuse,harassment,inappropriate,misinformation,other"
Reason::toOptions();         // ['spam' => 'Spam', 'abuse' => 'Abuse', ...]
```

### Status lifecycle

Every report carries a `Status` enum: `Pending`, `InReview`, `Resolved`, `Rejected`,
`Closed`, defaulting to `Pending`. Transitions are guarded by a transition table:

```php
use RoundlyConsulting\Reports\Enums\Status;

$report->changeStatusTo(Status::InReview); // allowed
$report->changeStatusTo(Status::InReview); // no-op when already in that status
```

An illegal transition throws `InvalidStatusTransitionException` when
`strict_transitions` is on. Resolve or reject through the facade to record metadata:

```php
Reports::resolve($report, by: $admin, note: 'Removed the post.');
Reports::reject($report, by: $admin, note: 'Not a violation.');
```

Both record the resolver, a timestamp, and the note, and fire `ReportResolved` /
`ReportRejected`.

### Moderation (multi-moderator sign-off)

By default a single call to `Reports::resolve()` / `reject()` settles a report immediately.
To require **several moderators to agree** first, open a moderation request — the report
becomes a [`approvals-for-laravel`](https://github.com/roundly-consulting/approvals-for-laravel)
subject and the engine's rule decides when the bar is met.

Moderators are any model using the approvals `GivesApprovals` trait:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

class User extends Model implements GivesApprovalsInterface
{
    use GivesApprovals;
}
```

Open a moderation request, then route decisions through the same `resolve()` / `reject()`:

```php
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Reports\Facades\Reports;

// Require 2 of the named moderators to agree.
Reports::moderate($report)
    ->requiring([$alice, $bob, $carol])
    ->rule(ApprovalRule::Quorum)
    ->quorum(2)
    ->open();

Reports::resolve($report, by: $alice);            // 1 of 2 — report stays open
Reports::resolve($report, by: $bob, note: 'Spam'); // quorum reached → Resolved
```

When the rule's threshold is reached the report's status is synced automatically (the
`SyncReportStatusFromApproval` listener), stamping the deciding moderator + reason and
re-emitting `ReportResolved` / `ReportRejected` / `ReportStatusChanged` — so the event
surface is identical whether a report was settled directly or through moderation. A single
rejection under the `Unanimous` rule rejects the report. Available rules: `Unanimous`,
`Quorum`, `Any`, `Weighted`.

> Resolving with a `null` actor, or an actor that can't give approvals, always settles the
> report immediately, even when a moderation request is open.

### Duplicate prevention

With `prevent_duplicates` on, a reporter (or guest identifier) cannot file a second report
against the same subject; the action throws `DuplicateReportException` carrying the existing
report. `duplicate_scope` controls whether this considers only open reports or all of them.

### Aggregation & querying

```php
$post->reportsCount();                 // total
$post->reportsCount(Status::Pending);  // a specific status
$post->hasBeenReported();
$post->isReportedBy($user);
$post->pendingReports;                 // open reports relation

Post::query()->withReportCounts()->get();              // eager reports_count
Post::query()->mostReported()->get();                  // by total report count
Post::query()->mostReported(Status::Pending)->get();   // count only pending
Post::query()->reportedMoreThan(5)->get();             // subjects over a threshold
```

The `Report` model also ships query scopes: `withStatus`, `withReason`, `pending`,
`resolved`, `rejected`, `open`.

### Threshold auto-actions

Set `config('reports.threshold')` to react when a subject crosses a reporting threshold. A
`ReportThresholdReached` event fires once, the moment the open report count reaches the
threshold:

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;

Event::listen(function (ReportThresholdReached $event): void {
    $event->subject->update(['is_hidden' => true]); // your app decides the action
    // $event->count, $event->threshold are also available
});
```

### Events

- `ReportCreated` — `report`.
- `ReportStatusChanged` — `report`, `statusBefore`, `statusNow`.
- `ReportResolved` — `report`.
- `ReportRejected` — `report`.
- `ReportThresholdReached` — `subject`, `count`, `threshold`.

```php
use RoundlyConsulting\Reports\Events\ReportCreated;

Event::listen(function (ReportCreated $event): void {
    $event->report;
});
```

### Commands

```bash
# Prune resolved/rejected/closed reports older than N days (soft-delete by default).
php artisan reports:prune --days=30

# Permanently delete instead of soft-deleting.
php artisan reports:prune --days=30 --force

# When prune_after_days is configured, --days is optional.
php artisan reports:prune

# List per-subject open report counts (optionally over a threshold).
php artisan reports:recount
php artisan reports:recount --threshold=5
```

### Soft deletes

The `Report` model uses soft deletes; `delete()` keeps the row and timestamps `deleted_at`.
Use `withTrashed()` / `restore()` as usual.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
