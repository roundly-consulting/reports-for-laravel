<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/reports-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reports-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/reports-for-laravel/main/art/hero.png" alt="Reports for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/reports-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/reports-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/reports-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/reports-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/reports-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/reports-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reports-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Reports for Laravel

Content moderation and flagging for Laravel. Let users or anonymous guests report any model with
a typed reason, move each report through a guarded status lifecycle, block duplicate reports,
require several moderators to agree before a report settles, and react when a subject crosses a
reporting threshold.

## Installation

Requires PHP 8.4, Laravel 12 or 13.

```bash
composer require roundly-consulting/reports-for-laravel
php artisan vendor:publish --tag="reports-migrations"
php artisan vendor:publish --tag="approvals-migrations"
php artisan migrate
```

The approvals migrations back moderation and are needed too. If your reporter, reportable or
resolver models have UUID/ULID keys, set `REPORTS_KEY_TYPE` **before** migrating. If your
moderators have UUID keys (`APPROVALS_KEY_TYPE=uuid`), set `REPORTS_PRIMARY_KEY_TYPE=uuid` too.

## Usage

Prepare your models:

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

File a report, then settle it:

```php
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Facades\Reports;

$report = Reports::report($post)
    ->by($user)
    ->for(Reason::Abuse)                       // a typed, validated reason
    ->because('This post violates the community guidelines.')
    ->create();                                // a second report by $user throws DuplicateReportException

Reports::review($report);                      // pending → in review
Reports::resolve($report, by: $moderator, note: 'Removed the post.');

$post->reportsCount();                         // 1
Post::query()->mostReported()->get();          // the most-reported posts first
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/reports-for-laravel](https://roundly-consulting.com/open-source/docs/reports-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reports-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reports-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=reports-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
