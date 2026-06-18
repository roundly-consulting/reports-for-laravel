<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\DataTransferObjects;

use Illuminate\Database\Eloquent\Model;

final readonly class ResolveReportData
{
    public function __construct(
        public ?Model $resolver = null,
        public ?string $note = null,
    ) {}
}
