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
        return SameModel::is($this->report, $report)
            && ($by === null || ($this->by !== null && SameModel::is($this->by, $by)))
            && ($note === null || $this->note === $note);
    }
}
