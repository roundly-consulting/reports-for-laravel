<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Actions\ChangeReportStatusAction;
use RoundlyConsulting\Reports\Actions\CreateReportAction;
use RoundlyConsulting\Reports\Actions\OpenModerationAction;
use RoundlyConsulting\Reports\Actions\PruneReportsAction;
use RoundlyConsulting\Reports\Actions\RejectReportAction;
use RoundlyConsulting\Reports\Actions\ResolveReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\PendingModeration;
use RoundlyConsulting\Reports\Support\PendingReport;
use RoundlyConsulting\Reports\Support\ReasonRegistry;

/**
 * The reports API: the root behind the {@see Facades\Reports} facade, and the class
 * to inject when you prefer dependency injection. Every method resolves its action
 * from the container, so host overrides apply; the builders, `Report::changeStatusTo()`
 * and the `GivesReports` trait all funnel through here.
 *
 * Not final on purpose: {@see Testing\ReportsFake} extends it so a constructor-
 * injected manager receives the fake under `Reports::fake()`.
 */
class ReportsManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * Start a report about the subject; finish it with `->by()`/`->asGuest()`,
     * `->for($reason)` and `->create()`.
     */
    public function report(Model $subject): PendingReport
    {
        return (new PendingReport($this))->about($subject);
    }

    /**
     * Start a report filed by the reporter; name the subject with `->about()`.
     */
    public function from(Model $reporter): PendingReport
    {
        return (new PendingReport($this))->by($reporter);
    }

    public function create(CreateReportData $data): Report
    {
        return $this->container->make(CreateReportAction::class)->execute($data);
    }

    /**
     * Start multi-moderator sign-off on the report; finish it with
     * `->requiring([...])->open()`.
     */
    public function moderate(Report $report): PendingModeration
    {
        return new PendingModeration($this, $report);
    }

    /**
     * The terminal of `moderate($report)->…->open()`.
     *
     * @internal
     *
     * @param  list<Model>  $moderators
     */
    public function openModeration(Report $report, array $moderators, ApprovalRule $rule, ?int $quorum = null): ApprovalRequest
    {
        return $this->container->make(OpenModerationAction::class)->execute($report, $moderators, $rule, $quorum);
    }

    public function resolve(Report $report, ?Model $by = null, ?string $note = null): Report
    {
        return $this->container->make(ResolveReportAction::class)
            ->execute($report, new ResolveReportData(resolver: $by, note: $note));
    }

    public function reject(Report $report, ?Model $by = null, ?string $note = null): Report
    {
        return $this->container->make(RejectReportAction::class)
            ->execute($report, new ResolveReportData(resolver: $by, note: $note));
    }

    /**
     * Move the report to a status, guarded by `reports.strict_transitions`.
     */
    public function changeStatus(Report $report, Status $status): Report
    {
        return $this->container->make(ChangeReportStatusAction::class)->execute($report, $status);
    }

    /**
     * Put the report in review (`Status::InReview`).
     */
    public function review(Report $report): Report
    {
        return $this->changeStatus($report, Status::InReview);
    }

    /**
     * Close the report (`Status::Closed`).
     */
    public function close(Report $report): Report
    {
        return $this->changeStatus($report, Status::Closed);
    }

    /**
     * Delete terminal (resolved / rejected / closed) reports created more than `$days`
     * days ago — `reports.prune_after_days` when omitted. Soft-deletes unless `$force`.
     *
     * @return int the number of reports pruned
     */
    public function prune(?int $days = null, bool $force = false): int
    {
        return $this->container->make(PruneReportsAction::class)->execute($days, $force);
    }

    /**
     * The allowed reasons, slug => human-friendly label.
     *
     * @return array<string, string>
     */
    public function reasons(): array
    {
        $registry = $this->registry();

        $reasons = [];

        foreach ($registry->all() as $slug) {
            $reasons[$slug] = $registry->label($slug);
        }

        return $reasons;
    }

    /**
     * The human-friendly label of a reason slug (the slug itself for a custom reason).
     */
    public function reasonLabel(string $slug): string
    {
        return $this->registry()->label($slug);
    }

    /**
     * The reason a report is filed under when none is given.
     */
    public function defaultReason(): string
    {
        return $this->registry()->default();
    }

    /**
     * Whether a report may be filed under the slug (always, with
     * `reports.allow_unknown_reasons`).
     */
    public function allowsReason(string $slug): bool
    {
        return $this->registry()->isAllowed($slug);
    }

    private function registry(): ReasonRegistry
    {
        return $this->container->make(ReasonRegistry::class);
    }
}
