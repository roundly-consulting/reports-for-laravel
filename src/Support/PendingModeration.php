<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;

/**
 * Fluent builder that opens an approvals request requiring N moderators to sign
 * off on a report before it resolves. Backed by the approvals engine's rules
 * (unanimous / quorum / any / weighted); `open()` goes through the manager.
 */
final class PendingModeration
{
    /** @var list<Model> */
    private array $moderators = [];

    private ApprovalRule $rule;

    private ?int $quorum;

    public function __construct(
        private readonly ReportsManager $manager,
        private readonly Report $report,
    ) {
        $this->rule = ReportsConfig::defaultRule();

        $this->quorum = ReportsConfig::defaultQuorum();
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
        return $this->manager->openModeration($this->report, $this->moderators, $this->rule, $this->quorum);
    }
}
