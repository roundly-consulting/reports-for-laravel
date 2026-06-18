<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Reports\Models\Report;

final class ReportCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Report $report,
    ) {}
}
