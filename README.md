# Reports for Laravel

Easily handle reports on Laravel entities. Let any model **give** reports (e.g. a `User`)
and any model **receive** them (e.g. a `Post`), track a report's lifecycle through a typed
status enum, and react to creation and status changes via events.

## Requirements

- PHP 8.3+
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

## Configuration

The published config file (`config/reports.php`) contains a single key:

```php
<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Models\Report;

return [

    'model' => Report::class,

];
```

| Key     | Type            | Default          | Purpose                                                                                 |
|---------|-----------------|------------------|-----------------------------------------------------------------------------------------|
| `model` | `class-string`  | `Report::class`  | The Eloquent model used to store reports. Point this at your own subclass to customise. |

The package works with zero configuration — publishing the config is only needed if you want
to swap in a custom model.

## Usage

### Preparing your models

Add the `GivesReports` trait to models that create reports, and the `HasReports` trait to
models that can be reported:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Traits\GivesReports;
use RoundlyConsulting\Reports\Traits\HasReports;

class User extends Model
{
    use GivesReports;
}

class Post extends Model
{
    use HasReports;
}
```

### Creating a report

```php
$post = Post::find(1);
$user = auth()->user();

$report = $user->giveReportTo(
    model: $post,
    description: 'This post violates the community guidelines.',
    type: 'abuse', // optional, defaults to "default"
);
```

`giveReportTo()` returns the created `RoundlyConsulting\Reports\Models\Report` instance.

### Reading reports

```php
// Reports written by the user (reporter side).
$user->givenReports;

// Reports filed against the post (reported side).
$post->reports;

// Order any HasReports model by how often it was reported.
$mostReported = Post::query()->mostReported()->get();
```

### Tracking status

Every report carries a `RoundlyConsulting\Reports\Enums\Status` enum
(`Status::New`, `Status::Solving`, `Status::Closed`), defaulting to `Status::New`:

```php
use RoundlyConsulting\Reports\Enums\Status;

$report->status; // Status::New

$report->changeStatusTo(Status::Solving);
```

Changing to the status the report is already in is a no-op (no event is dispatched).

### Events

The package dispatches package events you can listen to instead of hardcoding integrations:

- `RoundlyConsulting\Reports\Events\ReportCreated` — fired when a report is created.
  - `report` — the `Report` model.
- `RoundlyConsulting\Reports\Events\ReportStatusChanged` — fired when a report's status
  actually changes.
  - `report` — the `Report` model.
  - `statusBefore` — the `Status` before the change.
  - `statusNow` — the `Status` after the change.

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Events\ReportCreated;

Event::listen(function (ReportCreated $event): void {
    // Notify a moderator, increment a counter, etc.
    $event->report;
});
```

### Soft deletes

The `Report` model uses soft deletes, so `delete()` keeps the row and timestamps the
`deleted_at` column; use `withTrashed()` / `restore()` as usual.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
