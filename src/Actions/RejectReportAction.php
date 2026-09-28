<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Exceptions\ModeratorRequiredException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Rejects a report. While a moderation request is open, the rejection is recorded
 * through the approvals engine — only a moderator the request names (or a delegate of
 * one) may make it, its rule decides when the request is rejected, and
 * SyncReportStatusFromApproval owns the final status; anyone else, including a call
 * without an actor, gets a ModeratorRequiredException. Otherwise the report is
 * rejected immediately.
 */
final readonly class RejectReportAction
{
    public function __construct(private ChangeReportStatusAction $changeStatus) {}

    public function execute(Report $report, ResolveReportData $data): Report
    {
        if ($report->isUnderModeration()) {
            return $this->decideThroughModeration($report, $data);
        }

        $report->resolved_by_id = $data->resolver?->getKey();
        $report->resolved_by_type = $data->resolver?->getMorphClass();
        $report->resolution_note = $data->note;
        $report->resolved_at = Carbon::now();
        $report->save();

        $this->changeStatus->execute($report, Status::Rejected);

        event(new ReportRejected(report: $report));

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
            Approvals::for($report)->as($actor)->because($data->note)->reject();
        } catch (UnauthorizedApprovalException $e) {
            throw ModeratorRequiredException::notAModerator($report, $actor, $e);
        }

        return $report->refresh();
    }
}
