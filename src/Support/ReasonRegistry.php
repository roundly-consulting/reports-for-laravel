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
        $reasons = config('reports.reasons');

        if (! is_array($reasons) || $reasons === []) {
            return array_map(static fn (Reason $reason): string => $reason->value, Reason::cases());
        }

        return array_values(array_map(static fn (mixed $slug): string => (string) $slug, $reasons));
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
        return (bool) config('reports.allow_unknown_reasons', false);
    }

    public function default(): string
    {
        $default = config('reports.default_reason');

        return is_string($default) && $default !== '' ? $default : Reason::Other->value;
    }

    /**
     * The human-friendly label for a reason slug, falling back to the slug itself.
     */
    public function label(string $slug): string
    {
        $reason = Reason::tryFrom($slug);

        if ($reason instanceof Reason) {
            return $reason->label();
        }

        $key = "reports::reasons.{$slug}";
        $translation = trans($key);

        return is_string($translation) && $translation !== $key ? $translation : $slug;
    }
}
