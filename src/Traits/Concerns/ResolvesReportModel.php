<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits\Concerns;

use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReportModel;

/**
 * The configured report model for the host traits. One shared trait rather than a copy in
 * each: PHP applies the same trait method reaching a class through two paths without a
 * collision, so a model can use both GivesReports and HasReports.
 *
 * @internal
 */
trait ResolvesReportModel
{
    /**
     * @return class-string<Report>
     */
    private function reportModel(): string
    {
        return ReportModel::class();
    }
}
