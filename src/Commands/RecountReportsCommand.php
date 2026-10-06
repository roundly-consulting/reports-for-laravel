<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReportModel;

final class RecountReportsCommand extends Command
{
    protected $signature = 'reports:recount {--threshold= : Only list subjects with at least this many open reports}';

    protected $description = 'Recompute and list per-subject open report counts';

    public function handle(): int
    {
        // The raw input, not option(): the signature types it string|null, but an
        // Artisan::call() caller can pass an int.
        $threshold = $this->input->getOption('threshold');
        $threshold = is_int($threshold) ? (string) $threshold : $threshold;
        $minimum = 1;

        if (is_string($threshold) && $threshold !== '') {
            // A blunt (int) cast read "abc" and "-3" as "list everything" and "2.9" as 2.
            if (preg_match('/^\d+$/', $threshold) !== 1) {
                $this->components->error('--threshold must be a whole number (0 or more).');

                return self::FAILURE;
            }

            $minimum = (int) $threshold;
        }

        $rows = $this->newReport()->newQuery()
            ->whereIn('status', $this->openStatuses())
            ->selectRaw('reported_type, reported_id, count(*) as open_count')
            ->groupBy('reported_type', 'reported_id')
            ->havingRaw('count(*) >= ?', [$minimum])
            ->orderByDesc('open_count')
            ->get();

        if ($rows->isEmpty()) {
            $this->components->info('No subjects with open reports.');

            return self::SUCCESS;
        }

        $this->table(
            ['Subject type', 'Subject id', 'Open reports'],
            $rows->map(static fn (Report $row): array => [
                (string) $row->reported_type,
                (string) $row->reported_id,
                (string) $row->getAttribute('open_count'),
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function openStatuses(): array
    {
        return array_map(
            static fn (Status $status): string => $status->value,
            Status::open(),
        );
    }

    private function newReport(): Report
    {
        return ReportModel::new();
    }
}
