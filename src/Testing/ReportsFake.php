<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Testing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Approvals\Support\ApprovalRequestModelResolver;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;
use RoundlyConsulting\Reports\Support\ReportModel;

/**
 * Test double for the reports manager, installed by `Reports::fake()`. It extends the
 * manager, so injected managers keep type-checking, and it records every mutating
 * call instead of running it — whether it arrives through the facade, an injected
 * manager, the report/moderation builders, the prune command, `Report::changeStatusTo()`
 * or the GivesReports trait. Nothing is written: `create()` returns an unsaved report.
 * The reason reads (`reasons()`, `reasonLabel()`, …) still answer for real.
 */
final class ReportsFake extends ReportsManager
{
    /** @var list<CreateReportData> */
    private array $reported = [];

    /** @var list<Report> */
    private array $moderated = [];

    /** @var list<RecordedDecision> */
    private array $resolved = [];

    /** @var list<RecordedDecision> */
    private array $rejected = [];

    /** @var list<RecordedStatusChange> */
    private array $statusChanges = [];

    /** @var list<RecordedPrune> */
    private array $pruned = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function create(CreateReportData $data): Report
    {
        $this->reported[] = $data;

        $report = ReportModel::new();
        $report->forceFill([
            'reporter_id' => $data->reporter?->getKey(),
            'reporter_type' => $data->reporter?->getMorphClass(),
            'reported_id' => $data->subject->getKey(),
            'reported_type' => $data->subject->getMorphClass(),
            'reason' => $data->reason,
            'description' => $data->description,
            'guest_identifier' => $data->guestIdentifier,
            'status' => Status::Pending,
        ]);

        return $report;
    }

    /**
     * @internal
     *
     * @param  list<Model>  $moderators
     */
    public function openModeration(Report $report, array $moderators, ApprovalRule $rule, ?int $quorum = null): ApprovalRequest
    {
        $this->moderated[] = $report;

        $model = ApprovalRequestModelResolver::class();

        $request = new $model;
        $request->forceFill([
            'subject_id' => $report->getKey(),
            'subject_type' => $report->getMorphClass(),
            'rule' => $rule,
            'quorum' => $quorum,
            'required_approvers' => count($moderators),
            'status' => ApprovalStatus::Pending,
        ]);

        return $request;
    }

    public function resolve(Report $report, ?Model $by = null, ?string $note = null): Report
    {
        $this->resolved[] = new RecordedDecision($report, $by, $note);

        return $report;
    }

    public function reject(Report $report, ?Model $by = null, ?string $note = null): Report
    {
        $this->rejected[] = new RecordedDecision($report, $by, $note);

        return $report;
    }

    /**
     * Also records `review()` and `close()`, which go through here.
     */
    public function changeStatus(Report $report, Status $status): Report
    {
        $this->statusChanges[] = new RecordedStatusChange($report, $status);

        return $report;
    }

    public function prune(?int $days = null, bool $force = false): int
    {
        $this->pruned[] = new RecordedPrune($days, $force);

        // Nothing ran, so nothing was pruned.
        return 0;
    }

    /**
     * @param  Model|null  $by  when given, the reporter must match
     * @param  Reason|string|null  $reason  when given, the reason must match
     */
    public function assertReported(Model $subject, ?Model $by = null, Reason|string|null $reason = null): void
    {
        $slug = $reason instanceof Reason ? $reason->value : $reason;

        $matching = array_filter(
            $this->reported,
            static fn (CreateReportData $data): bool => $data->subject->is($subject)
                && ($by === null || ($data->reporter !== null && $data->reporter->is($by)))
                && ($slug === null || $data->reason === $slug),
        );

        PHPUnit::assertNotEmpty($matching, 'Expected the subject to be reported'.$this->describe($by, 'by the given reporter', $slug, 'for').'.');
    }

    public function assertNothingReported(): void
    {
        PHPUnit::assertSame([], $this->reported, sprintf('Expected no report to be filed, but %d were.', count($this->reported)));
    }

    public function assertModerated(Report $report): void
    {
        $matching = array_filter($this->moderated, static fn (Report $recorded): bool => $recorded->is($report));

        PHPUnit::assertNotEmpty($matching, 'Expected moderation to be opened for the report.');
    }

    public function assertNothingModerated(): void
    {
        PHPUnit::assertSame([], $this->moderated, 'Expected no moderation to be opened.');
    }

    /**
     * @param  Model|null  $by  when given, the resolver must match
     * @param  string|null  $note  when given, the note must match
     */
    public function assertResolved(Report $report, ?Model $by = null, ?string $note = null): void
    {
        PHPUnit::assertTrue(
            $this->decided($this->resolved, $report, $by, $note),
            'Expected the report to be resolved'.$this->describe($by, 'by the given actor', $note, 'with note').'.',
        );
    }

    public function assertNothingResolved(): void
    {
        PHPUnit::assertSame([], $this->resolved, 'Expected no report to be resolved.');
    }

    /**
     * @param  Model|null  $by  when given, the rejecting actor must match
     * @param  string|null  $note  when given, the note must match
     */
    public function assertRejected(Report $report, ?Model $by = null, ?string $note = null): void
    {
        PHPUnit::assertTrue(
            $this->decided($this->rejected, $report, $by, $note),
            'Expected the report to be rejected'.$this->describe($by, 'by the given actor', $note, 'with note').'.',
        );
    }

    public function assertNothingRejected(): void
    {
        PHPUnit::assertSame([], $this->rejected, 'Expected no report to be rejected.');
    }

    /**
     * @param  Status|null  $to  when given, the target status must match
     */
    public function assertStatusChanged(Report $report, ?Status $to = null): void
    {
        $matching = array_filter(
            $this->statusChanges,
            static fn (RecordedStatusChange $change): bool => $change->matches($report, $to),
        );

        PHPUnit::assertNotEmpty($matching, $to === null
            ? 'Expected the report status to change.'
            : "Expected the report status to change to [{$to->value}].");
    }

    public function assertNothingStatusChanged(): void
    {
        PHPUnit::assertSame([], $this->statusChanges, 'Expected no report status to change.');
    }

    /**
     * @param  int|null  $days  when given, the prune window must match (null = the config default was used)
     * @param  bool|null  $force  when given, the force flag must match
     */
    public function assertPruned(?int $days = null, ?bool $force = null): void
    {
        $matching = array_filter(
            $this->pruned,
            static fn (RecordedPrune $prune): bool => $prune->matches($days, $force),
        );

        PHPUnit::assertNotEmpty($matching, 'Expected reports to be pruned'
            .($days === null ? '' : " with a {$days}-day window")
            .($force === null ? '' : ($force ? ' permanently' : ' softly'))
            .'.');
    }

    public function assertNothingPruned(): void
    {
        PHPUnit::assertSame([], $this->pruned, 'Expected no reports to be pruned.');
    }

    /**
     * @param  list<RecordedDecision>  $records
     */
    private function decided(array $records, Report $report, ?Model $by, ?string $note): bool
    {
        foreach ($records as $record) {
            if ($record->matches($report, $by, $note)) {
                return true;
            }
        }

        return false;
    }

    private function describe(?Model $by, string $byText, ?string $value, string $valueText): string
    {
        return ($by === null ? '' : " {$byText}")
            .($value === null ? '' : " {$valueText} [{$value}]");
    }
}
