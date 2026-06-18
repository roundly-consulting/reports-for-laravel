<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

final class RecountReportsCommand extends Command
{
    protected $signature = 'reports:recount {--threshold= : Only list subjects with at least this many open reports}';

    protected $description = 'Recompute and list per-subject open report counts';

    public function handle(): int
    {
        $threshold = $this->option('threshold');
        $minimum = ($threshold !== null && $threshold !== '') ? (int) $threshold : 1;

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
        /** @var class-string<Report> $model */
        $model = config('reports.model', Report::class);

        return new $model;
    }
}
