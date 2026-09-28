<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Rejects a report. When the report has an open moderation request and the actor
 * can give approvals, the rejection is recorded through the approvals engine — its
 * rule decides when the request is rejected and SyncReportStatusFromApproval owns the
 * final status. Otherwise the report is rejected immediately (the original behaviour).
 */
final readonly class RejectReportAction
{
    public function __construct(private ChangeReportStatusAction $changeStatus) {}

    public function execute(Report $report, ResolveReportData $data): Report
    {
        $actor = $data->resolver;

        if ($actor instanceof GivesApprovalsInterface && $this->hasOpenModeration($report)) {
            $pending = Approvals::for($report)->as($actor);

            if ($data->note !== null) {
                $pending->because($data->note);
            }

            $pending->reject();

            return $report->refresh();
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

    private function hasOpenModeration(Report $report): bool
    {
        return $report->approvalRequests()
            ->where('status', ApprovalStatus::Pending)
            ->exists();
    }
}
