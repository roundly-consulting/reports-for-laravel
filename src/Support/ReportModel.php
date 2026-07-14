<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;
use RoundlyConsulting\Reports\Models\Report;

/**
 * The single seam through which the package resolves the `reports.model` class.
 *
 * Wraps the toolkit's {@see ModelResolver} (which validates the configured value
 * really is an Eloquent model, throwing otherwise) and narrows it to the
 * package's own base class: the package calls `Report`'s own API — the status
 * lifecycle, the open/pending scopes, the approvals subject contract — so a real
 * Eloquent model that is not a `Report` cannot serve, and falls back to the
 * packaged model rather than failing at the first `changeStatusTo()`.
 */
final class ReportModel
{
    /**
     * @return class-string<Report>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('reports.model', Report::class);

        return is_a($model, Report::class, true) ? $model : Report::class;
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
