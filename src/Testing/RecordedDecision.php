<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Testing;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Models\Report;

/**
 * One resolve / reject call captured by {@see ReportsFake}.
 */
final readonly class RecordedDecision
{
    public function __construct(
        public Report $report,
        public ?Model $by,
        public ?string $note,
    ) {}

    public function matches(Report $report, ?Model $by, ?string $note): bool
    {
        return $this->report->is($report)
            && ($by === null || ($this->by !== null && $this->by->is($by)))
            && ($note === null || $this->note === $note);
    }
}
