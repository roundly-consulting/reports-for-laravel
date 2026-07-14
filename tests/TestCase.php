<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Approvals\ApprovalsServiceProvider;
use RoundlyConsulting\Reports\ReportsServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            ApprovalsServiceProvider::class,
            ReportsServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        // The approvals engine tables back the report moderation flow (Report is an approvals subject).
        $approvalsPackage = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($approvalsPackage.'/database/migrations');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
