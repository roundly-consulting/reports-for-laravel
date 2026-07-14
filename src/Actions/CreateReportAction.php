<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\UnknownReportReasonException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportModel;

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

        $this->checkThreshold($data);

        return $report;
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

    private function checkThreshold(CreateReportData $data): void
    {
        $threshold = config('reports.threshold');

        if (! is_int($threshold) || $threshold <= 0) {
            return;
        }

        $count = $this->openReportsCountFor($data);

        // Fire exactly once, the moment the open count reaches the threshold.
        if ($count === $threshold) {
            event(new ReportThresholdReached(
                subject: $data->subject,
                count: $count,
                threshold: $threshold,
            ));
        }
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
