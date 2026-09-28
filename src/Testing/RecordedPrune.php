<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Testing;

/**
 * One prune call captured by {@see ReportsFake}.
 */
final readonly class RecordedPrune
{
    public function __construct(
        public ?int $days,
        public bool $force,
    ) {}

    public function matches(?int $days, ?bool $force): bool
    {
        return ($days === null || $this->days === $days)
            && ($force === null || $this->force === $force);
    }
}
