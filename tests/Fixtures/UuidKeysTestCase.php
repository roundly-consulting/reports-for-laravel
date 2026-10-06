<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests\Fixtures;

use RoundlyConsulting\Reports\Tests\TestCase;

/**
 * A host whose moderators have UUID keys, set before the providers boot and the migrations
 * run — the only window that matters, since the migrations read the key types. Approvals
 * keys its actor AND subject columns by one `approvals.key_type`, so the report (the
 * subject) needs a uuid id too: `reports.primary_key_type`. The resolver morph follows
 * the moderators, so `reports.key_type` is uuid as well.
 */
abstract class UuidKeysTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'approvals.key_type' => 'uuid',
            'reports.key_type' => 'uuid',
            'reports.primary_key_type' => 'uuid',
        ]);
    }
}
