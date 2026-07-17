<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider reports needs, in registration order. Approvals is a hard `require`
     * a host would auto-discover, and the moderation flow genuinely runs on it (a Report
     * is an approvals subject), so listing it is what makes the test env match a real
     * install rather than a fiction.
     *
     * `enums-for-laravel` and `package-toolkit-for-laravel` are hard `require`s too but
     * the first ships no provider (helpers only) and the second is a base class rather
     * than a registered package, so the list is genuinely two entries.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            ApprovalsServiceProvider::class,
            ReportsServiceProvider::class,
        ];
    }

    /**
     * The migrations, named by **provider class** — never by filename.
     *
     * This replaces a hand-rolled `defineDatabaseMigrations()` that reflected on
     * ApprovalsServiceProvider to find its package root and then guessed
     * `/database/migrations` beneath it. That is exactly what the base case's
     * `LoadsProviderMigrations` concern does once, correctly, for the whole fleet.
     *
     * It also used `loadMigrationsFrom()`, which is what makes Testbench reset state by
     * running `migrate:rollback` after each test. Every roundly migration is forward-only
     * by standard, so `Migrator` skips the missing `down()` *silently* — harmless on
     * sqlite `:memory:` (the database dies with the connection), fatal on a real engine,
     * where the tables survive and the next test dies creating them again while naming an
     * innocent migration. The base case drops every table and re-migrates instead.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            ApprovalsServiceProvider::class,
            ReportsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }
}
