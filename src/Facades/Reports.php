<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\PendingReport;
use RoundlyConsulting\Reports\Support\ReportsManager;

/**
 * @method static PendingReport report(Model $subject)
 * @method static PendingReport from(Model $reporter)
 * @method static Report resolve(Report $report, ?Model $by = null, ?string $note = null)
 * @method static Report reject(Report $report, ?Model $by = null, ?string $note = null)
 * @method static list<string> reasons()
 *
 * @see ReportsManager
 */
final class Reports extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ReportsManager::class;
    }
}
