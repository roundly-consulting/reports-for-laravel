<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

use RoundlyConsulting\Reports\Models\Report;

final class MissingModeratorsException extends ReportsException
{
    public static function forReport(Report $report): self
    {
        return new self(
            "Cannot open moderation for report [{$report->getKey()}] without any moderators.",
        );
    }
}
