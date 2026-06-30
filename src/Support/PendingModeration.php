<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Models\Report;

/**
 * Fluent builder that opens an approvals request requiring N moderators to sign
 * off on a report before it resolves. Backed by the approvals engine's rules
 * (unanimous / quorum / any / weighted).
 */
final class PendingModeration
{
    /** @var list<Model> */
    private array $moderators = [];

    private ApprovalRule $rule;

    private ?int $quorum;

    public function __construct(private readonly Report $report)
    {
        $rule = config('reports.moderation.default_rule', 'unanimous');
        $this->rule = ApprovalRule::tryFrom(is_string($rule) ? $rule : 'unanimous') ?? ApprovalRule::Unanimous;

        $quorum = config('reports.moderation.default_quorum');
        $this->quorum = is_int($quorum) ? $quorum : null;
    }

    /**
     * The moderators whose sign-off the report requires.
     *
     * @param  list<Model>  $moderators
     */
    public function requiring(array $moderators): self
    {
        $this->moderators = $moderators;

        return $this;
    }

    public function rule(ApprovalRule $rule): self
    {
        $this->rule = $rule;

        return $this;
    }

    public function quorum(int $quorum): self
    {
        $this->quorum = $quorum;

        return $this;
    }

    /**
     * Open the moderation request, returning the underlying approval request.
     */
    public function open(): ApprovalRequest
    {
        if ($this->moderators === []) {
            throw MissingModeratorsException::forReport($this->report);
        }

        return $this->report->requestApproval($this->moderators, $this->rule, $this->quorum);
    }
}
