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
        // The raw input, not option(): the signature types it string|null, but an
        // Artisan::call() caller can pass an int.
        $option = $this->input->getOption('days');
        $option = is_int($option) ? (string) $option : $option;
        $force = (bool) $this->option('force');

        $days = null;

        if (is_string($option) && $option !== '') {
            // A blunt (int) cast read "abc" as 0 — prune everything — and "-5" as a
            // cutoff in the future.
            if (preg_match('/^\d+$/', $option) !== 1) {
                $this->components->error('--days must be a whole number of days (0 or more).');

                return self::FAILURE;
            }

            $days = (int) $option;
        }

        try {
            $count = $reports->prune($days, $force);
        } catch (MissingPruneWindowException) {
            $this->components->error('No --days given and reports.prune_after_days is not configured.');

            return self::FAILURE;
        }

        $verb = $force ? 'permanently deleted' : 'soft-deleted';
        $this->components->info("Pruned {$count} report(s) ({$verb}).");

        return self::SUCCESS;
    }
}
