<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\Fixtures\UuidModeratorTestModel;

/**
 * Moderation on a host with UUID moderators. Approvals keys its subject columns by the same
 * `approvals.key_type` as its actor columns, so with uuid moderators the report id lands in
 * a uuid column. With `reports.id` always bigint, PostgreSQL refused the very first write
 * (`invalid input syntax for type uuid`) — under either approvals key type, since a bigint
 * actor column refuses the moderators instead. SQLite and MySQL store the bigint id as text
 * and hide it, so only the pgsql leg can fail this; it runs everywhere as a smoke test.
 */
beforeEach(function (): void {
    Schema::create('uuid_moderators', function (Blueprint $table): void {
        $table->uuid('id')->primary();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('uuid_moderators');
});

it('moderates a report with uuid moderators when the report id is a uuid', function (): void {
    $alice = UuidModeratorTestModel::query()->create();
    $bob = UuidModeratorTestModel::query()->create();
    $report = Report::factory()->pending()->create();

    Reports::moderate($report)->requiring([$alice, $bob])->rule(ApprovalRule::Quorum)->quorum(2)->open();

    Reports::resolve($report, $alice);
    Reports::resolve($report, $bob, 'Removed.');

    $fresh = $report->fresh();

    expect(Str::isUuid($report->getKey()))->toBeTrue()
        ->and($fresh?->status)->toBe(Status::Resolved)
        ->and($fresh?->resolution_note)->toBe('Removed.')
        ->and($fresh?->resolved_by_id)->toBe($bob->getKey())
        ->and($fresh?->isUnderModeration())->toBeFalse();
});
