<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Tests\Fixtures\CustomReportModel;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

/**
 * The model-swap proof (S) for `reports.model`, driven through the REAL flows.
 *
 * `tests/Feature/ConfiguredModelsTest.php` covers the same seam and is kept — it asserts
 * real domain behaviour through the host model. But it sets `reports.model` in a
 * `beforeEach`, i.e. **in the test body**, after the providers have booted and the
 * migrations have run. That is precisely the shape the reviews row found to be
 * structurally incapable of catching the bug it was named for: a real host sets the key in
 * `config/reports.php`, before boot, and the provider hangs its ApprovalRequestResolved
 * listener at boot. A body-time swap re-reads the config on each resolver call and looks
 * green while every boot-time decision still points at the packaged class.
 *
 * So this file is not a duplicate: it is the same seam proved from the state a host
 * actually produces, via {@see SwappedReportTestCase}, which this directory is bound to
 * (Pest binds a test case per directory, not per file). It also adds what
 * ConfiguredModelsTest structurally cannot: `CountsCreations` proves each row was created
 * **as** the host class — `instanceof` passes for a row created as the packaged Report,
 * which would fire none of the host's model events (permissions #31).
 */
it('honours a host report model through the filing flow', function (): void {
    expect('reports.model')->toHonourModelSwap(CustomReportModel::class, function (): array {
        $user = UserTestModel::query()->create();
        $post = PostTestModel::query()->create();
        $other = PostTestModel::query()->create();

        // The flows a host actually calls — the fluent builder and the trait helper.
        $viaBuilder = Reports::report($post)->by($user)->for(Reason::Abuse)->create();
        $viaTrait = $user->giveReportTo($other, 'spam');

        return [
            $viaBuilder,
            $viaTrait,
            // The morph relations hydrate through the seam too, not just the writes.
            ...$post->reports()->get()->all(),
            ...$user->givenReports()->get()->all(),
        ];
    });
});

/**
 * The resolution flow is the seam most worth proving separately: it is the package
 * writing on the host's behalf, and it runs through the boot-time listener rather than a
 * direct call. A swap honoured on create but bypassed on resolve would write status
 * through a different class than the one that filed the report.
 */
it('honours the host model when the package resolves a report', function (): void {
    $user = UserTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $report = $user->giveReportTo($post, 'spam');

    Reports::resolve($report, $user, 'handled');

    expect($report->refresh())->toBeInstanceOf(CustomReportModel::class)
        ->and($report->refresh()->status)->toBe(Status::Resolved)
        ->and($post->reports()->first())->toBeInstanceOf(CustomReportModel::class);
});

/**
 * The duplicate guard and the aggregation scopes read through the same seam they were
 * written through. If they queried the packaged model, the answer would come from a
 * different class than the one that wrote the rows.
 */
it('answers duplicate and aggregation queries through the swapped model', function (): void {
    $user = UserTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $user->giveReportTo($post, 'spam');

    expect($post->hasBeenReported())->toBeTrue()
        ->and($post->isReportedBy($user))->toBeTrue()
        ->and($post->reportsCount())->toBe(1)
        ->and($post->reports()->first())->toBeInstanceOf(CustomReportModel::class);
});

// The structural half of the seam — `Report` is non-final (reports #33 shipped it final),
// and `reports.model` really defaults to the packaged model — is pinned once in
// tests/ArchTest.php by `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does
// NOT live here: that preset asserts the config *default*, which this directory has
// swapped away.
