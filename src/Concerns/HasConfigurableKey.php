<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUniqueStringIds;
use Illuminate\Support\Str;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

/**
 * Drives the report's primary key from `reports.primary_key_type`, matching the column type
 * the migration emitted for the same config value.
 *
 * This is the **inbound** key — the reports table's own `id`, which approvals' subject
 * columns point at when a report is moderated. It is distinct from `reports.key_type`, the
 * **outbound** key of the reporter / reported / resolver morph columns.
 *
 * Laravel's {@see HasUniqueStringIds} keys `getKeyType()`, `getIncrementing()`, `uniqueIds()`
 * and route-binding validation off the single `$usesUniqueIds` flag, so flipping that flag
 * in the trait initializer is the whole seam: on `bigint` the model behaves exactly as if it
 * had never used the trait.
 *
 * @internal
 */
trait HasConfigurableKey
{
    use HasUniqueStringIds;

    /**
     * Turn Laravel's unique-string-id machinery off entirely on the `bigint` default, so
     * `getKeyType()` / `getIncrementing()` fall through to the auto-incrementing parent.
     */
    public function initializeHasUniqueStringIds(): void
    {
        $this->usesUniqueIds = $this->configuredKeyType() !== KeyType::BigInt;
    }

    /**
     * Generate a key of the configured type. Never called on `bigint` — the database mints
     * those.
     */
    public function newUniqueId(): string
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? (string) Str::ulid()
            : (string) Str::uuid7();
    }

    /**
     * The configured primary-key type of the reports table.
     */
    public function configuredKeyType(): KeyType
    {
        return KeyType::fromConfig('reports.primary_key_type');
    }

    protected function isValidUniqueId(mixed $value): bool
    {
        return $this->configuredKeyType() === KeyType::Ulid
            ? Str::isUlid($value)
            : Str::isUuid($value);
    }
}
