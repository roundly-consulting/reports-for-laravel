<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Resolves a report. When the report has an open moderation request and the actor
 * can give approvals, the decision is recorded through the approvals engine — its
 * rule decides when the bar is met and SyncReportStatusFromApproval owns the final
 * status. Otherwise the report resolves immediately (the original behaviour).
 */
final class ResolveReportAction
{
    public function execute(Report $report, ResolveReportData $data): Report
    {
        $actor = $data->resolver;

        if ($actor instanceof GivesApprovalsInterface && $this->hasOpenModeration($report)) {
            $pending = Approvals::for($report)->as($actor);

            if ($data->note !== null) {
                $pending->because($data->note);
            }

            $pending->approve();

            return $report->refresh();
        }

        $report->resolved_by_id = $data->resolver?->getKey();
        $report->resolved_by_type = $data->resolver?->getMorphClass();
        $report->resolution_note = $data->note;
        $report->resolved_at = Carbon::now();
        $report->save();

        $report->changeStatusTo(Status::Resolved);

        event(new ReportResolved(report: $report));

        return $report;
    }

    private function hasOpenModeration(Report $report): bool
    {
        return $report->approvalRequests()
            ->where('status', ApprovalStatus::Pending)
            ->exists();
    }
}
