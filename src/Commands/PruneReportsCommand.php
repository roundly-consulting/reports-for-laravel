<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

final class PruneReportsCommand extends Command
{
    protected $signature = 'reports:prune
        {--days= : Prune terminal reports older than this many days (defaults to config)}
        {--force : Permanently delete instead of soft-deleting}';

    protected $description = 'Prune old, resolved/rejected/closed reports';

    public function handle(): int
    {
        $days = $this->resolveDays();

        if ($days === null) {
            $this->components->error('No --days given and reports.prune_after_days is not configured.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subDays($days);

        $query = $this->newReport()->newQuery()
            ->whereIn('status', $this->terminalStatuses())
            ->where('created_at', '<', $cutoff);

        $force = (bool) $this->option('force');

        $count = $force
            ? (int) $query->forceDelete()
            : (int) $query->delete();

        $verb = $force ? 'permanently deleted' : 'soft-deleted';
        $this->components->info("Pruned {$count} report(s) ({$verb}).");

        return self::SUCCESS;
    }

    private function resolveDays(): ?int
    {
        $option = $this->option('days');

        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        $configured = config('reports.prune_after_days');

        return is_int($configured) ? $configured : null;
    }

    /**
     * @return list<string>
     */
    private function terminalStatuses(): array
    {
        return [
            Status::Resolved->value,
            Status::Rejected->value,
            Status::Closed->value,
        ];
    }

    private function newReport(): Report
    {
        /** @var class-string<Report> $model */
        $model = config('reports.model', Report::class);

        return new $model;
    }
}
