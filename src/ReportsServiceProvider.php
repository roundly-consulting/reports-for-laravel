<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports;

use Closure;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Reports\Commands\PruneReportsCommand;
use RoundlyConsulting\Reports\Commands\RecountReportsCommand;
use RoundlyConsulting\Reports\Listeners\SyncReportStatusFromApproval;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportModel;
use RoundlyConsulting\Reports\Support\ReportsConfig;

final class ReportsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('reports')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasCommands([
                PruneReportsCommand::class,
                RecountReportsCommand::class,
            ])
            ->contributesToAbout(fn (): array => $this->aboutPayload());
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ReasonRegistry::class);
        $this->app->singleton(ReportsManager::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migration's key-type-aware morph columns are macros, so they must
        // exist before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();

        Event::listen(ApprovalRequestResolved::class, SyncReportStatusFromApproval::class);
    }

    /**
     * What `php artisan about` reports.
     *
     * Report content is user data: a reason slug is the host's moderation
     * vocabulary and a report names a reporter and a subject. So this section
     * renders switches, bounds and counts only — never a reason, never a
     * threshold subject, never a stored report.
     *
     * @return array<string, string>
     */
    private function aboutPayload(): array
    {
        $reasons = app(ReasonRegistry::class);
        $table = config('reports.table');
        $rule = config('reports.moderation.default_rule', 'unanimous');

        return [
            'Model' => class_basename(ReportModel::class()),
            'Table' => is_string($table) ? $table : 'reports',
            'Key type' => KeyType::fromConfig('reports.key_type')->value,
            'Reasons' => sprintf(
                '%d allowed, unknown %s',
                count($reasons->all()),
                $reasons->allowsUnknown() ? 'ACCEPTED' : 'REJECTED',
            ),
            'Default reason' => $reasons->default() === 'other' ? 'DEFAULT' : 'CUSTOM',
            'Duplicate prevention' => $this->duplicatePrevention(),
            'Strict transitions' => ReportsConfig::strictTransitions() ? 'ON' : 'OFF',
            'Threshold' => $this->describe(static function (): string {
                $threshold = ReportsConfig::threshold();

                return $threshold === null ? 'DISABLED' : $threshold.' open report(s)';
            }),
            'Pruning' => $this->describe(static function (): string {
                $days = ReportsConfig::pruneAfterDays();

                return $days === null ? 'MANUAL' : $days.' day(s)';
            }),
            'Moderation' => sprintf(
                '%s rule, quorum %s',
                is_string($rule) ? $rule : 'unanimous',
                (string) (ReportsConfig::defaultQuorum() ?? 'ALL MODERATORS'),
            ),
        ];
    }

    /**
     * A config line for `about`: a misconfigured value renders INVALID instead of
     * taking the whole command down.
     *
     * @param  Closure(): string  $render
     */
    private function describe(Closure $render): string
    {
        try {
            return $render();
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    private function duplicatePrevention(): string
    {
        if (! ReportsConfig::preventDuplicates()) {
            return 'OFF';
        }

        $scope = config('reports.duplicate_scope');

        return 'ON (scope '.($scope === 'any' ? 'any' : 'open').')';
    }
}
