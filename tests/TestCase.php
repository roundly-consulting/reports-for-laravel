<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests;

use Illuminate\Database\Migrations\Migration;
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
        $this->loadApprovalsSchema();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Run the approvals engine migrations in dependency order; their tables back the
     * report moderation flow (Report is an approvals subject).
     */
    private function loadApprovalsSchema(): void
    {
        $base = dirname((string) (new ReflectionClass(ApprovalsServiceProvider::class))->getFileName(), 2);

        $migrations = [
            'create_approvals_table',
            'create_approval_requests_table',
            'add_v11_columns_to_approvals_table',
            'add_staging_to_approval_requests_table',
            'create_approval_request_stages_table',
            'create_approval_delegations_table',
        ];

        foreach ($migrations as $name) {
            $migration = require "{$base}/database/migrations/{$name}.php";

            if ($migration instanceof Migration) {
                $migration->up();
            }
        }
    }
}
