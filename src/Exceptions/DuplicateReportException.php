<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

use RoundlyConsulting\Reports\Models\Report;

final class DuplicateReportException extends ReportsException
{
    public function __construct(
        public readonly Report $existing,
    ) {
        parent::__construct(
            "A report against this subject already exists [{$existing->getKey()}].",
        );
    }

    public static function for(Report $existing): self
    {
        return new self($existing);
    }
}
