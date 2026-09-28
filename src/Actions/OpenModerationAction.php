<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\ApprovalsManager;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Opens an approvals request requiring the moderators to sign off on the report.
 * Their decisions (Reports::resolve()/reject() by a moderator) count towards it, and
 * SyncReportStatusFromApproval moves the report once the rule resolves it.
 */
final readonly class OpenModerationAction
{
    public function __construct(private ApprovalsManager $approvals) {}

    /**
     * @param  list<Model>  $moderators
     */
    public function execute(Report $report, array $moderators, ApprovalRule $rule, ?int $quorum = null): ApprovalRequest
    {
        if ($moderators === []) {
            throw MissingModeratorsException::forReport($report);
        }

        return $this->approvals->request($report)->from($moderators)->rule($rule, $quorum)->open();
    }
}
