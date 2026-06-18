<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ReportThresholdReached
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $subject,
        public int $count,
        public int $threshold,
    ) {}
}
