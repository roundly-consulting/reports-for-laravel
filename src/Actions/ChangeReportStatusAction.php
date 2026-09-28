<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Moves a report to a status and fires ReportStatusChanged. Staying on the current
 * status is a no-op; with `reports.strict_transitions` on, a move the Status graph
 * does not allow throws InvalidStatusTransitionException.
 */
final class ChangeReportStatusAction
{
    public function execute(Report $report, Status $status): Report
    {
        if ($report->status === $status) {
            return $report;
        }

        if ($this->strictTransitions() && ! $report->status->canTransitionTo($status)) {
            throw InvalidStatusTransitionException::for($report, $report->status, $status);
        }

        $previousStatus = $report->status;

        $report->update([
            'status' => $status,
        ]);

        event(new ReportStatusChanged(
            report: $report,
            statusBefore: $previousStatus,
            statusNow: $report->status,
        ));

        return $report;
    }

    private function strictTransitions(): bool
    {
        return (bool) config('reports.strict_transitions', true);
    }
}
