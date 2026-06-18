<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

final class UnknownReportReasonException extends ReportsException
{
    /**
     * @param  list<string>  $allowed
     */
    public function __construct(
        public readonly string $slug,
        public readonly array $allowed,
    ) {
        $allowedList = implode(', ', $allowed);

        parent::__construct(
            "Unknown report reason [{$slug}]. Allowed reasons: [{$allowedList}].",
        );
    }

    /**
     * @param  list<string>  $allowed
     */
    public static function slug(string $slug, array $allowed): self
    {
        return new self($slug, $allowed);
    }
}
