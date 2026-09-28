<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Reports\Exceptions\MissingPruneWindowException;
use RoundlyConsulting\Reports\ReportsManager;

final class PruneReportsCommand extends Command
{
    protected $signature = 'reports:prune
        {--days= : Prune terminal reports older than this many days (defaults to config)}
        {--force : Permanently delete instead of soft-deleting}';

    protected $description = 'Prune old, resolved/rejected/closed reports';

    public function handle(ReportsManager $reports): int
    {
        $option = $this->option('days');
        $force = (bool) $this->option('force');

        try {
            $count = $reports->prune(
                $option !== null && $option !== '' ? (int) $option : null,
                $force,
            );
        } catch (MissingPruneWindowException) {
            $this->components->error('No --days given and reports.prune_after_days is not configured.');

            return self::FAILURE;
        }

        $verb = $force ? 'permanently deleted' : 'soft-deleted';
        $this->components->info("Pruned {$count} report(s) ({$verb}).");

        return self::SUCCESS;
    }
}
