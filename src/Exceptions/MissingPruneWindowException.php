<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

final class MissingPruneWindowException extends ReportsException
{
    public static function make(): self
    {
        return new self('No prune window given and reports.prune_after_days is not configured.');
    }
}
