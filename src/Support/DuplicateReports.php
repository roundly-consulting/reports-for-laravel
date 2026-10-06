<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

/**
 * The duplicate rule of a new filing (`reports.prevent_duplicates` / `duplicate_scope`),
 * shared by CreateReportAction and the fake. A filing duplicates an earlier report about
 * the same subject by the same reporter — or, for a guest, with the same guest identifier —
 * that is still open (the `open` scope) or ever filed (`any`). An anonymous filing without a
 * guest identifier has nothing to compare against and never duplicates.
 *
 * @internal
 */
final class DuplicateReports
{
    /**
     * The stored report the filing duplicates, or null.
     *
     * @throws InvalidConfigurationException for an unknown duplicate scope
     */
    public static function stored(CreateReportData $data): ?Report
    {
        if (! self::checks($data)) {
            return null;
        }

        $query = ReportModel::query()
            ->where('reported_type', $data->subject->getMorphClass())
            ->where('reported_id', $data->subject->getKey());

        if ($data->reporter !== null) {
            $query->where('reporter_type', $data->reporter->getMorphClass())
                ->where('reporter_id', $data->reporter->getKey());
        } else {
            $query->whereNull('reporter_id')
                ->where('guest_identifier', $data->guestIdentifier);
        }

        if (self::openOnly()) {
            $query->whereIn('status', array_map(
                static fn (Status $status): string => $status->value,
                Status::open(),
            ));
        }

        return $query->first();
    }

    /**
     * Whether an earlier filing, now on `$status`, is one the new filing duplicates — the
     * stored lookup's rule, applied to a filing that was never stored.
     *
     * @throws InvalidConfigurationException for an unknown duplicate scope
     */
    public static function matches(CreateReportData $earlier, Status $status, CreateReportData $data): bool
    {
        if (! self::checks($data) || (self::openOnly() && $status->isTerminal())) {
            return false;
        }

        if (! self::sameRow($earlier->subject, $data->subject)) {
            return false;
        }

        return $data->reporter !== null
            ? $earlier->reporter !== null && self::sameRow($earlier->reporter, $data->reporter)
            : $earlier->reporter === null && $earlier->guestIdentifier === $data->guestIdentifier;
    }

    /**
     * Whether duplicates are prevented and the filing has something to dedupe on.
     */
    private static function checks(CreateReportData $data): bool
    {
        return ReportsConfig::preventDuplicates()
            && ($data->reporter !== null || $data->guestIdentifier !== null);
    }

    /**
     * @throws InvalidConfigurationException for an unknown duplicate scope
     */
    private static function openOnly(): bool
    {
        return ReportsConfig::duplicateScope() === ReportsConfig::SCOPE_OPEN;
    }

    /**
     * Same morph type and key: what the stored lookup compares.
     */
    private static function sameRow(Model $a, Model $b): bool
    {
        return $a->getMorphClass() === $b->getMorphClass() && $a->getKey() === $b->getKey();
    }
}
