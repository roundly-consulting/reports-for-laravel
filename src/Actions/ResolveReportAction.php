<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Exceptions\ModeratorRequiredException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Resolves a report. While a moderation request is open, the decision is recorded
 * through the approvals engine — only a moderator the request names (or a delegate of
 * one) may make it, its rule decides when the bar is met, and
 * SyncReportStatusFromApproval owns the final status; anyone else, including a call
 * without an actor, gets a ModeratorRequiredException. Otherwise the report resolves
 * immediately, stamping the resolver, the note and the time; resolving it again is a
 * no-op that keeps the first decision.
 */
final readonly class ResolveReportAction
{
    public function __construct(private TransitionReportAction $transition) {}

    public function execute(Report $report, ResolveReportData $data): Report
    {
        if ($report->isUnderModeration()) {
            return $this->decideThroughModeration($report, $data);
        }

        // Checked against the stored status and written in one locked step: a refused
        // move writes nothing, and a report already resolved is left as it is.
        if ($this->transition->execute($report, Status::Resolved, $data) instanceof Status) {
            event(new ReportResolved(report: $report));
        }

        return $report;
    }

    /**
     * Record the actor's decision on the open moderation request; the approvals engine
     * refuses anyone the request does not name (nor a delegate of one).
     *
     * @throws ModeratorRequiredException for a null actor or a non-moderator
     */
    private function decideThroughModeration(Report $report, ResolveReportData $data): Report
    {
        $actor = $data->resolver ?? throw ModeratorRequiredException::withoutActor($report);

        try {
            Approvals::for($report)->as($actor)->because($data->note)->approve();
        } catch (UnauthorizedApprovalException $e) {
            throw ModeratorRequiredException::notAModerator($report, $actor, $e);
        }

        return $report->refresh();
    }
}
