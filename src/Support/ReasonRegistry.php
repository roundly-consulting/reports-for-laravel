<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use RoundlyConsulting\Reports\Enums\Reason;

final class ReasonRegistry
{
    /**
     * All allowed reason slugs.
     *
     * @return list<string>
     */
    public function all(): array
    {
        return ReportsConfig::reasons();
    }

    public function isAllowed(string $slug): bool
    {
        if ($this->allowsUnknown()) {
            return true;
        }

        return in_array($slug, $this->all(), strict: true);
    }

    public function allowsUnknown(): bool
    {
        return ReportsConfig::allowUnknownReasons();
    }

    public function default(): string
    {
        return ReportsConfig::defaultReason();
    }

    /**
     * The human-friendly label for a reason slug. Known reasons read their readable
     * label from the enums Helpers trait; custom slugs fall back to the slug itself.
     */
    public function label(string $slug): string
    {
        return Reason::tryFrom($slug)?->label() ?? $slug;
    }
}
