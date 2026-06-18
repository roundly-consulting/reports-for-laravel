<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Actions\CreateReportAction;
use RoundlyConsulting\Reports\Actions\RejectReportAction;
use RoundlyConsulting\Reports\Actions\ResolveReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Models\Report;

final class ReportsManager
{
    public function __construct(
        private readonly CreateReportAction $createReport,
        private readonly ResolveReportAction $resolveReport,
        private readonly RejectReportAction $rejectReport,
        private readonly ReasonRegistry $reasons,
    ) {}

    public function report(Model $subject): PendingReport
    {
        return (new PendingReport($this->createReport, $this->reasons))->about($subject);
    }

    public function from(Model $reporter): PendingReport
    {
        return (new PendingReport($this->createReport, $this->reasons))->by($reporter);
    }

    public function resolve(Report $report, ?Model $by = null, ?string $note = null): Report
    {
        return $this->resolveReport->execute($report, new ResolveReportData(resolver: $by, note: $note));
    }

    public function reject(Report $report, ?Model $by = null, ?string $note = null): Report
    {
        return $this->rejectReport->execute($report, new ResolveReportData(resolver: $by, note: $note));
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return $this->reasons->all();
    }
}
