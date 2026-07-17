<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests\Fixtures;

use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * The host subclass `reports.model` invites, used to prove the seam is real.
 *
 * `CountsCreations` is what makes the proof independent of `instanceof`: it counts rows
 * created as *this exact class*, so a report row created as the packaged Report — which
 * would still satisfy `instanceof` while firing none of the host's model events
 * (permissions #31) — cannot be mistaken for an honoured swap.
 *
 * Distinct from `Tests\Models\CustomReport`, which is `final` and exists only to pin that
 * `Report` stays extendable (reports #33 shipped `final` on this very model).
 */
class CustomReportModel extends Report
{
    use CountsCreations;

    protected $table = 'reports';
}
