<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\MissingPruneWindowException;
use RoundlyConsulting\Reports\Support\ReportModel;
use RoundlyConsulting\Reports\Support\ReportsConfig;

/**
 * Deletes terminal (resolved / rejected / closed) reports created before the cutoff.
 * Soft-deletes by default; `$force` deletes permanently.
 */
final class PruneReportsAction
{
    /**
     * @param  int|null  $days  the age cutoff in days; `reports.prune_after_days` when null
     * @return int the number of reports pruned
     *
     * @throws MissingPruneWindowException when neither `$days` nor the config is set
     */
    public function execute(?int $days = null, bool $force = false): int
    {
        $days ??= ReportsConfig::pruneAfterDays();

        if ($days === null) {
            throw MissingPruneWindowException::make();
        }

        $query = ReportModel::query()
            ->whereIn('status', $this->terminalStatuses())
            ->where('created_at', '<', Carbon::now()->subDays($days));

        return $force
            ? (int) $query->forceDelete()
            : (int) $query->delete();
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
}
