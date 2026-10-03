<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Reports\Models\Report;

/**
 * The single seam through which the package resolves the `reports.model` class.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class ReportModel
{
    /**
     * @return class-string<Report>
     */
    public static function class(): string
    {
        return ModelResolver::for('reports.model', Report::class);
    }

    public static function new(): Report
    {
        $class = self::class();

        return new $class;
    }

    /**
     * @return Builder<Report>
     */
    public static function query(): Builder
    {
        return self::new()->newQuery();
    }
}
