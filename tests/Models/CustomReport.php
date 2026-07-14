<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests\Models;

use RoundlyConsulting\Reports\Models\Report;

/**
 * A host's own report model — exactly what `config('reports.model')` documents,
 * and what `final` on the packaged model used to make impossible.
 */
final class CustomReport extends Report
{
    public function isHostModel(): bool
    {
        return true;
    }
}
