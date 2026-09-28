<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;
use RoundlyConsulting\Reports\Support\PendingModeration;
use RoundlyConsulting\Reports\Support\PendingReport;
use RoundlyConsulting\Reports\Testing\ReportsFake;

/**
 * @method static PendingReport report(Model $subject)
 * @method static PendingReport from(Model $reporter)
 * @method static Report create(CreateReportData $data)
 * @method static PendingModeration moderate(Report $report)
 * @method static Report resolve(Report $report, ?Model $by = null, ?string $note = null)
 * @method static Report reject(Report $report, ?Model $by = null, ?string $note = null)
 * @method static Report changeStatus(Report $report, Status $status)
 * @method static Report review(Report $report)
 * @method static Report close(Report $report)
 * @method static int prune(?int $days = null, bool $force = false)
 * @method static array<string, string> reasons()
 * @method static string reasonLabel(string $slug)
 * @method static string defaultReason()
 * @method static bool allowsReason(string $slug)
 * @method static ReportsFake fake()
 * @method static void assertReported(Model $subject, ?Model $by = null, Reason|string|null $reason = null)
 * @method static void assertNothingReported()
 * @method static void assertModerated(Report $report)
 * @method static void assertNothingModerated()
 * @method static void assertResolved(Report $report, ?Model $by = null, ?string $note = null)
 * @method static void assertNothingResolved()
 * @method static void assertRejected(Report $report, ?Model $by = null, ?string $note = null)
 * @method static void assertNothingRejected()
 * @method static void assertStatusChanged(Report $report, ?Status $to = null)
 * @method static void assertNothingStatusChanged()
 * @method static void assertPruned(?int $days = null, ?bool $force = null)
 * @method static void assertNothingPruned()
 *
 * @see ReportsManager
 * @see ReportsFake
 */
final class Reports extends Facade
{
    /**
     * Swap in a recording fake behind the facade and the container. Every mutating
     * call — through the facade, an injected manager, the builders, the commands,
     * `Report::changeStatusTo()` or the GivesReports trait — is recorded instead of run.
     */
    public static function fake(): ReportsFake
    {
        $fake = app(ReportsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return ReportsManager::class;
    }
}
