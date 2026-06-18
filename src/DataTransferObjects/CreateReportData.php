<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Enums\Reason;

final readonly class CreateReportData
{
    public string $reason;

    public function __construct(
        public Model $subject,
        Reason|string $reason,
        public ?Model $reporter = null,
        public ?string $description = null,
        public ?string $guestIdentifier = null,
    ) {
        $this->reason = $reason instanceof Reason ? $reason->value : $reason;
    }
}
