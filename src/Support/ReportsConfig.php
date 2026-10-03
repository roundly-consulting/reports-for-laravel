<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\Reports\Enums\Reason;

/**
 * Strict, typed reads of the scalar `reports.*` keys. Every env value is a string, so an
 * integer key accepts a canonical integer string (`'2'`) and a switch accepts the boolean
 * words `filter_var` knows (`'false'`, `'0'`, `'off'`, `'no'`, …). A key that is not set —
 * absent, null, or blank like a host's `KEY=` — takes its default, or for an optional key
 * (threshold, prune window, quorum) none. Anything else that isn't usable — `'two'`, `'1.5'`,
 * a rule or scope typo, a non-string table name — throws the toolkit's
 * InvalidConfigurationException rather than quietly picking a side.
 *
 * @internal
 */
final class ReportsConfig
{
    public const string SCOPE_OPEN = 'open';

    public const string SCOPE_ANY = 'any';

    /**
     * The open-report count that fires ReportThresholdReached; null when disabled
     * (not set — absent, null or blank — or zero).
     *
     * @throws InvalidConfigurationException
     */
    public static function threshold(): ?int
    {
        $threshold = self::optionalInt('reports.threshold', min: 0);

        return $threshold === 0 ? null : $threshold;
    }

    /**
     * The default prune window in days; null when not set (absent, null or blank), so
     * pruning without `--days` raises MissingPruneWindowException instead of guessing.
     * Never `0` for a blank value: that would prune every terminal report.
     *
     * @throws InvalidConfigurationException
     */
    public static function pruneAfterDays(): ?int
    {
        return self::optionalInt('reports.prune_after_days', min: 0);
    }

    /**
     * The default approval count of the quorum rule; null (every moderator) when not set
     * (absent, null or blank).
     *
     * @throws InvalidConfigurationException
     */
    public static function defaultQuorum(): ?int
    {
        return self::optionalInt('reports.moderation.default_quorum', min: 1);
    }

    /**
     * The default approval rule of a moderation request; `unanimous` when unset.
     *
     * @throws InvalidConfigurationException
     */
    public static function defaultRule(): ApprovalRule
    {
        return Config::enum('reports.moderation.default_rule', ApprovalRule::class, ApprovalRule::Unanimous);
    }

    /**
     * `open` (dedupe against non-terminal reports) or `any` (every report ever filed).
     *
     * @throws InvalidConfigurationException
     */
    public static function duplicateScope(): string
    {
        return Config::oneOf('reports.duplicate_scope', [self::SCOPE_OPEN, self::SCOPE_ANY], self::SCOPE_OPEN);
    }

    /**
     * @throws InvalidConfigurationException
     */
    public static function table(): string
    {
        return self::string('reports.table', 'reports');
    }

    /**
     * The allowed reason slugs; the `Reason` enum values when unset.
     *
     * @return list<string>
     *
     * @throws InvalidConfigurationException
     */
    public static function reasons(): array
    {
        $key = 'reports.reasons';
        $reasons = self::unlessBlank(config($key));

        if ($reasons === null) {
            return array_map(static fn (Reason $reason): string => $reason->value, Reason::cases());
        }

        if (! is_array($reasons) || $reasons === [] || ! array_is_list($reasons)) {
            throw new InvalidConfigurationException(
                "Configuration value [{$key}] must be a non-empty list of reason slugs, [".self::describe($reasons).'] given.',
            );
        }

        $slugs = [];

        foreach ($reasons as $slug) {
            if (! is_string($slug) || trim($slug) === '') {
                throw new InvalidConfigurationException(
                    "Configuration value [{$key}] must be a non-empty list of reason slugs, [".self::describe($slug).'] given.',
                );
            }

            $slugs[] = $slug;
        }

        return $slugs;
    }

    /**
     * The reason used when a report is filed without one; `other` when unset.
     *
     * @throws InvalidConfigurationException
     */
    public static function defaultReason(): string
    {
        return self::string('reports.default_reason', Reason::Other->value);
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
        return self::unlessBlank(config($key)) === null ? null : Config::integer($key, $min, min: $min);
    }

    /**
     * @throws InvalidConfigurationException
     */
    private static function string(string $key, string $default): string
    {
        $value = self::unlessBlank(config($key));

        if ($value === null) {
            return $default;
        }

        if (! is_string($value)) {
            throw InvalidConfigurationException::notAString($key, $value);
        }

        return $value;
    }

    /**
     * A raw config value, with a blank string (`''` or whitespace — a host's `KEY=`) read as
     * null: not set, exactly like an absent key.
     */
    private static function unlessBlank(mixed $value): mixed
    {
        return is_string($value) && trim($value) === '' ? null : $value;
    }

    private static function describe(mixed $value): string
    {
        return match (true) {
            $value === '' => "''",
            is_string($value) => $value,
            is_int($value), is_float($value), is_bool($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
