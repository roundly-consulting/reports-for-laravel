<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Listeners;

use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Actions\TransitionReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Mirrors an approval request's resolution onto the reports Status of its subject, so
 * engine-driven outcomes (quorum reached, a direct decision, a staged pipeline clearing)
 * move the report and re-emit the reports event surface — keeping that surface unchanged
 * regardless of how the moderation decision arrived. Idempotent and transition-guard
 * aware: a report already on the outcome, or one the strict graph can't move there, is
 * left untouched.
 */
final readonly class SyncReportStatusFromApproval
{
    public function __construct(private TransitionReportAction $transition) {}

    public function handle(ApprovalRequestResolved $event): void
    {
        $approvalRequest = $event->request;
        $subject = $approvalRequest->subject;

        if (! $subject instanceof Report) {
            return;
        }

        $target = $this->map($approvalRequest->status);

        if ($target === null) {
            return;
        }

        try {
            $moved = $this->transition->execute($subject, $target, $this->decision($approvalRequest));
        } catch (InvalidStatusTransitionException) {
            return;
        }

        if (! $moved instanceof Status) {
            return;
        }

        if ($target === Status::Resolved) {
            event(new ReportResolved(report: $subject));
        } else {
            event(new ReportRejected(report: $subject));
        }
    }

    /**
     * The deciding moderator and their reason: the latest decision matching the outcome.
     */
    private function decision(ApprovalRequest $approvalRequest): ResolveReportData
    {
        $decision = $approvalRequest->decisions()
            ->where('status', $approvalRequest->status)
            ->latest('id')
            ->first();

        return new ResolveReportData(resolver: $decision?->actor, note: $decision?->reason);
    }

    private function map(ApprovalStatus $status): ?Status
    {
        return match ($status) {
            ApprovalStatus::Approved => Status::Resolved,
            ApprovalStatus::Rejected => Status::Rejected,
            ApprovalStatus::Cancelled, ApprovalStatus::Expired, ApprovalStatus::Pending => null,
        };
    }
}
