<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\UnknownReportReasonException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportModel;

/**
 * Files a report. The reason is validated, then — inside one transaction holding the
 * reported subject's row lock — duplicates are refused (`reports.prevent_duplicates` /
 * `duplicate_scope`), the report is inserted and, with `reports.threshold` set, the
 * subject's open reports are counted. ReportThresholdReached fires after the commit.
 */
final class CreateReportAction
{
    public function __construct(
        private readonly ReasonRegistry $reasons,
    ) {}

    public function execute(CreateReportData $data): Report
    {
        if (! $this->reasons->isAllowed($data->reason)) {
            throw UnknownReportReasonException::slug($data->reason, $this->reasons->all());
        }

        $openCount = null;

        $file = function () use ($data, &$openCount): Report {
            $this->lockSubject($data->subject);

            $this->guardAgainstDuplicate($data);

            /** @var Report $report */
            $report = $this->newReport()->newInstance([
                'reporter_id' => $data->reporter?->getKey(),
                'reporter_type' => $data->reporter?->getMorphClass(),
                'reported_id' => $data->subject->getKey(),
                'reported_type' => $data->subject->getMorphClass(),
                'reason' => $data->reason,
                'description' => $data->description,
                'guest_identifier' => $data->guestIdentifier,
                'status' => Status::Pending,
            ]);

            $report->save();

            $openCount = $this->threshold() === null ? null : $this->openReportsCountFor($data);

            return $report;
        };

        $reports = $this->newReport()->getConnection();
        $subjects = $data->subject->getConnection();

        // One transaction holds the subject lock across the lookup, the insert and the
        // count. A subject on another connection is locked in a transaction of its own
        // around the reports one.
        $report = $subjects === $reports
            ? $reports->transaction($file)
            : $subjects->transaction(static fn (): Report => $reports->transaction($file));

        if (is_int($openCount)) {
            $this->announceThreshold($data, $openCount);
        }

        return $report;
    }

    /**
     * Take the reported subject's row lock for the rest of the transaction. Filings
     * against one subject therefore serialize: the next one's duplicate lookup and
     * threshold count see this one's committed row, so a double-submit can't file twice
     * and a crossing is counted exactly once. (SQLite has no row locks; it serializes
     * writers instead.)
     */
    private function lockSubject(Model $subject): void
    {
        if (! $subject->exists) {
            return;
        }

        $subject->newQueryWithoutScopes()
            ->whereKey($subject->getKey())
            ->lockForUpdate()
            ->value($subject->getKeyName());
    }

    private function guardAgainstDuplicate(CreateReportData $data): void
    {
        if (! (bool) config('reports.prevent_duplicates', true)) {
            return;
        }

        $query = $this->newReport()->newQuery()
            ->where('reported_type', $data->subject->getMorphClass())
            ->where('reported_id', $data->subject->getKey());

        if ($data->reporter !== null) {
            $query->where('reporter_type', $data->reporter->getMorphClass())
                ->where('reporter_id', $data->reporter->getKey());
        } elseif ($data->guestIdentifier !== null) {
            $query->whereNull('reporter_id')
                ->where('guest_identifier', $data->guestIdentifier);
        } else {
            // Anonymous report with no dedupe key: nothing to compare against.
            return;
        }

        if (config('reports.duplicate_scope') === 'open') {
            $query->whereIn('status', array_map(
                static fn (Status $status): string => $status->value,
                Status::open(),
            ));
        }

        $existing = $query->first();

        if ($existing instanceof Report) {
            throw DuplicateReportException::for($existing);
        }
    }

    /**
     * Fire ReportThresholdReached when this filing brought the subject's open count to
     * exactly the threshold — once per crossing: resolving or rejecting reports lowers
     * the count, and a later filing that climbs back to the threshold fires again.
     */
    private function announceThreshold(CreateReportData $data, int $openCount): void
    {
        $threshold = $this->threshold();

        if ($threshold === null || $openCount !== $threshold) {
            return;
        }

        event(new ReportThresholdReached(
            subject: $data->subject,
            count: $openCount,
            threshold: $threshold,
        ));
    }

    /**
     * The configured threshold, or null when it is disabled (null, zero or negative).
     */
    private function threshold(): ?int
    {
        $threshold = config('reports.threshold');

        return is_int($threshold) && $threshold > 0 ? $threshold : null;
    }

    private function openReportsCountFor(CreateReportData $data): int
    {
        return $this->newReport()->newQuery()
            ->where('reported_type', $data->subject->getMorphClass())
            ->where('reported_id', $data->subject->getKey())
            ->whereIn('status', array_map(
                static fn (Status $status): string => $status->value,
                Status::open(),
            ))
            ->count();
    }

    private function newReport(): Report
    {
        return ReportModel::new();
    }
}
