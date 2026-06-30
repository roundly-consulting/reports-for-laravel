<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Reports\Commands\PruneReportsCommand;
use RoundlyConsulting\Reports\Commands\RecountReportsCommand;
use RoundlyConsulting\Reports\Listeners\SyncReportStatusFromApproval;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportsManager;

final class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reports.php', 'reports');

        $this->app->singleton(ReasonRegistry::class);
        $this->app->singleton(ReportsManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Event::listen(ApprovalRequestResolved::class, SyncReportStatusFromApproval::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneReportsCommand::class,
                RecountReportsCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/reports.php' => config_path('reports.php'),
            ], 'reports-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'reports-migrations');
        }
    }
}
