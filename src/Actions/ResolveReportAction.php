<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Models\Report;

final class ResolveReportAction
{
    public function execute(Report $report, ResolveReportData $data): Report
    {
        $report->resolved_by_id = $data->resolver?->getKey();
        $report->resolved_by_type = $data->resolver?->getMorphClass();
        $report->resolution_note = $data->note;
        $report->resolved_at = Carbon::now();
        $report->save();

        $report->changeStatusTo(Status::Resolved);

        event(new ReportResolved(report: $report));

        return $report;
    }
}
