<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Moderation can't be opened on the report: it is already settled, or a moderation request
 * on it is still pending. Either would leave the report stuck — a settled report can't
 * take the moderators' outcome, and a second request keeps it under moderation after the
 * first one decided it. Nothing is opened.
 */
final class ModerationNotAllowedException extends ReportsException
{
    public function __construct(
        public readonly Report $report,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function settled(Report $report, Status $status): self
    {
        return new self(
            $report,
            "Cannot open moderation for report [{$report->getKey()}]: it is already settled [{$status->value}].",
        );
    }

    public static function alreadyOpen(Report $report): self
    {
        return new self(
            $report,
            "Cannot open moderation for report [{$report->getKey()}]: it already has a pending moderation request.",
        );
    }
}
