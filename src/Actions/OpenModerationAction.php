<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\ApprovalsManager;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Exceptions\ModerationNotAllowedException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Opens an approvals request requiring the moderators to sign off on the report.
 * Their decisions (Reports::resolve()/reject() by a moderator) count towards it, and
 * SyncReportStatusFromApproval moves the report once the rule resolves it.
 *
 * Only an open report (pending or in review) with no pending moderation request can be
 * moderated. Both are checked under the report's row lock, against its stored status, so
 * two concurrent opens can't both pass.
 */
final readonly class OpenModerationAction
{
    public function __construct(private ApprovalsManager $approvals) {}

    /**
     * @param  list<Model>  $moderators
     *
     * @throws MissingModeratorsException
     * @throws ModerationNotAllowedException for a settled report, or one already under moderation
     */
    public function execute(Report $report, array $moderators, ApprovalRule $rule, ?int $quorum = null): ApprovalRequest
    {
        if ($moderators === []) {
            throw MissingModeratorsException::forReport($report);
        }

        return $report->getConnection()->transaction(function () use ($report, $moderators, $rule, $quorum): ApprovalRequest {
            $status = $report->newQueryWithoutScopes()
                ->whereKey($report->getKey())
                ->lockForUpdate()
                ->first()->status ?? $report->status;

            if (! $status->isOpen()) {
                throw ModerationNotAllowedException::settled($report, $status);
            }

            if ($report->isUnderModeration()) {
                throw ModerationNotAllowedException::alreadyOpen($report);
            }

            return $this->approvals->request($report)->from($moderators)->rule($rule, $quorum)->open();
        });
    }
}
