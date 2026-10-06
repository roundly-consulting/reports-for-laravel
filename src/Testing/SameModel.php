<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Testing;

use Illuminate\Database\Eloquent\Model;

/**
 * Model identity for {@see ReportsFake}'s assertions. `Model::is()` compares keys, so any
 * two unsaved models (both keys null) matched — and the fake's own reports are unsaved.
 * An unsaved model only ever matches itself.
 *
 * @internal
 */
final class SameModel
{
    public static function is(Model $recorded, Model $expected): bool
    {
        return $recorded === $expected
            || ($recorded->exists && $expected->exists && $recorded->is($expected));
    }
}
