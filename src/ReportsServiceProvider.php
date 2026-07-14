<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports;

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\Reports\Commands\PruneReportsCommand;
use RoundlyConsulting\Reports\Commands\RecountReportsCommand;
use RoundlyConsulting\Reports\Listeners\SyncReportStatusFromApproval;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportModel;
use RoundlyConsulting\Reports\Support\ReportsManager;

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
        $threshold = config('reports.threshold');
        $pruneAfter = config('reports.prune_after_days');
        $quorum = config('reports.moderation.default_quorum');
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
            'Strict transitions' => config('reports.strict_transitions', true) === false ? 'OFF' : 'ON',
            'Threshold' => is_int($threshold) && $threshold > 0 ? $threshold.' open report(s)' : 'DISABLED',
            'Pruning' => is_int($pruneAfter) ? $pruneAfter.' day(s)' : 'MANUAL',
            'Moderation' => sprintf(
                '%s rule, quorum %s',
                is_string($rule) ? $rule : 'unanimous',
                is_int($quorum) ? (string) $quorum : 'ALL MODERATORS',
            ),
        ];
    }

    private function duplicatePrevention(): string
    {
        if (config('reports.prevent_duplicates', true) === false) {
            return 'OFF';
        }

        $scope = config('reports.duplicate_scope');

        return 'ON (scope '.($scope === 'any' ? 'any' : 'open').')';
    }
}
