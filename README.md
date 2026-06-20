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
each report with a typed, translatable reason, move it through a guarded status lifecycle,
prevent duplicate spam, surface aggregation insights, and react to threshold crossings and
lifecycle changes via events — with a fluent facade, Action classes, and DTOs underneath.

It depends only on Laravel itself (no third-party runtime dependencies).

## Requirements

- PHP 8.4+
- Laravel 12 or 13

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

Optionally publish the reason translations to customise their labels:

```bash
php artisan vendor:publish --tag="reports-translations"
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
];
```

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
    ->for(Reason::Abuse)            // typed, validated, translatable label
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
`allow_unknown_reasons` is `true`). Default reasons map to the `Reason` enum, which exposes
a translatable label:

```php
use RoundlyConsulting\Reports\Enums\Reason;

Reason::Abuse->label(); // "Abuse" (translatable via reports::reasons.abuse)
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
