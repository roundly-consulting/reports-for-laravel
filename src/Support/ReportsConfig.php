<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Typed reads of the scalar `reports.*` keys, tolerant of env strings: every env value
 * is a string, so an integer key accepts an integer string (`'2'`) and a switch accepts
 * the boolean words `filter_var` knows (`'false'`, `'0'`, `'off'`, `'no'`, …). An empty
 * value counts as unset. `threshold` and `prune_after_days` holding anything else throw
 * the toolkit's InvalidConfigurationException rather than quietly switching the feature
 * off.
 *
 * @internal
 */
final class ReportsConfig
{
    /**
     * The open-report count that fires ReportThresholdReached; null when disabled
     * (unset, empty or zero).
     *
     * @throws InvalidConfigurationException
     */
    public static function threshold(): ?int
    {
        $threshold = self::optionalInt('reports.threshold', min: 0);

        return $threshold === 0 ? null : $threshold;
    }

    /**
     * The default prune window in days; null when unset or empty.
     *
     * @throws InvalidConfigurationException
     */
    public static function pruneAfterDays(): ?int
    {
        return self::optionalInt('reports.prune_after_days', min: 0);
    }

    /**
     * The default approval count of the quorum rule; null (every moderator) when unset,
     * empty — or invalid: like `default_rule`, a bad moderation default falls back to
     * the strictest setting rather than failing the builder.
     */
    public static function defaultQuorum(): ?int
    {
        try {
            return self::optionalInt('reports.moderation.default_quorum', min: 1);
        } catch (InvalidConfigurationException) {
            return null;
        }
    }

    public static function strictTransitions(): bool
    {
        return Config::boolean('reports.strict_transitions', true);
    }

    public static function preventDuplicates(): bool
    {
        return Config::boolean('reports.prevent_duplicates', true);
    }

    public static function allowUnknownReasons(): bool
    {
        return Config::boolean('reports.allow_unknown_reasons', false);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private static function optionalInt(string $key, int $min): ?int
    {
        $value = config($key);

        if ($value === null || $value === '') {
            return null;
        }

        return Config::intBetween($key, $min, PHP_INT_MAX, $min);
    }
}
