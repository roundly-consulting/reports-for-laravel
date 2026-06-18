<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

final class InvalidStatusTransitionException extends ReportsException
{
    public function __construct(
        public readonly Report $report,
        public readonly Status $from,
        public readonly Status $to,
    ) {
        parent::__construct(
            "Cannot transition report [{$report->getKey()}] from [{$from->value}] to [{$to->value}].",
        );
    }

    public static function for(Report $report, Status $from, Status $to): self
    {
        return new self($report, $from, $to);
    }
}
