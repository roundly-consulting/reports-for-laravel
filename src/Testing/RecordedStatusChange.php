<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Testing;

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

/**
 * One changeStatus / review / close call captured by {@see ReportsFake}.
 */
final readonly class RecordedStatusChange
{
    public function __construct(
        public Report $report,
        public Status $status,
    ) {}

    public function matches(Report $report, ?Status $to): bool
    {
        return $this->report->is($report) && ($to === null || $this->status === $to);
    }
}
