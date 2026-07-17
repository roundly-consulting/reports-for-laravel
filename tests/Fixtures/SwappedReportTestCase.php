<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests\Fixtures;

use RoundlyConsulting\Reports\Tests\TestCase;

/**
 * The suite's base case with `reports.model` already pointed at the host subclass BEFORE
 * the providers boot.
 *
 * Boot order is the whole point, and this suite is a worked example of why. Its existing
 * swap coverage (`tests/Feature/ConfiguredModelsTest.php`) sets `reports.model` in a
 * `beforeEach` — i.e. in the test body, after the providers have booted and after the
 * migrations have run. That is precisely the shape the reviews row found: a swap test
 * structurally incapable of catching the bug it is named for, because a real host sets
 * the key in `config/reports.php`, before boot. The provider hangs its
 * ApprovalRequestResolved listener at boot, and that listener writes report status.
 *
 * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * whatever the base case wires — the same decapitation an un-parented `defineEnvironment()`
 * causes one level up. The parent is empty today; that is not a reason to omit it.
 */
abstract class SwappedReportTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'reports.model' => CustomReportModel::class,
        ]);
    }
}
