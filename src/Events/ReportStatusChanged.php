<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

final class ReportStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Report $report,
        public Status $statusBefore,
        public Status $statusNow,
    ) {}
}
