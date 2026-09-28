<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Exceptions\ModeratorRequiredException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Moves a report to a status and fires ReportStatusChanged. Staying on the current
 * status is a no-op; with `reports.strict_transitions` on, a move the Status graph
 * does not allow throws InvalidStatusTransitionException. Reopening a settled report
 * (or sending it back into review) clears its resolution. While a moderation request
 * is open, a move out of the open statuses throws ModeratorRequiredException — the
 * moderators settle the report, through resolve()/reject().
 */
final readonly class ChangeReportStatusAction
{
    public function __construct(private TransitionReportAction $transition) {}

    /**
     * @throws InvalidStatusTransitionException
     * @throws ModeratorRequiredException
     */
    public function execute(Report $report, Status $status): Report
    {
        if ($status->isTerminal() && $report->isUnderModeration()) {
            throw ModeratorRequiredException::withoutActor($report);
        }

        $this->transition->execute($report, $status);

        return $report;
    }
}
