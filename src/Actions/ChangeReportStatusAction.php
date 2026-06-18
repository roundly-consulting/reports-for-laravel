<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

final class ChangeReportStatusAction
{
    public function execute(Report $report, Status $status): Report
    {
        return $report->changeStatusTo($status);
    }
}
