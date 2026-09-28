<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Listeners;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Actions\ChangeReportStatusAction;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Mirrors an approval request's resolution onto the reports Status of its subject, so
 * engine-driven outcomes (quorum reached, a direct decision, a staged pipeline clearing)
 * move the report and re-emit the reports event surface — keeping that surface unchanged
 * regardless of how the moderation decision arrived. Idempotent and transition-guard aware.
 */
final readonly class SyncReportStatusFromApproval
{
    public function __construct(private ChangeReportStatusAction $changeStatus) {}

    public function handle(ApprovalRequestResolved $event): void
    {
        $approvalRequest = $event->request;
        $subject = $approvalRequest->subject;

        if (! $subject instanceof Report) {
            return;
        }

        $target = $this->map($approvalRequest->status);

        if ($target === null || $subject->status === $target) {
            return;
        }

        if ($this->strict() && ! $subject->status->canTransitionTo($target)) {
            return;
        }

        $this->stampDecision($subject, $approvalRequest);

        $this->changeStatus->execute($subject, $target);

        if ($target === Status::Resolved) {
            event(new ReportResolved(report: $subject));
        } elseif ($target === Status::Rejected) {
            event(new ReportRejected(report: $subject));
        }
    }

    private function stampDecision(Report $report, ApprovalRequest $approvalRequest): void
    {
        $decision = $approvalRequest->decisions()
            ->where('status', $approvalRequest->status)
            ->latest('id')
            ->first();

        $actor = $decision?->actor;

        $report->resolved_by_id = $actor?->getKey();
        $report->resolved_by_type = $actor?->getMorphClass();
        $report->resolution_note = $decision?->reason;
        $report->resolved_at = Carbon::now();
        $report->save();
    }

    private function map(ApprovalStatus $status): ?Status
    {
        return match ($status) {
            ApprovalStatus::Approved => Status::Resolved,
            ApprovalStatus::Rejected => Status::Rejected,
            ApprovalStatus::Cancelled, ApprovalStatus::Expired, ApprovalStatus::Pending => null,
        };
    }

    private function strict(): bool
    {
        return (bool) config('reports.strict_transitions', true);
    }
}
