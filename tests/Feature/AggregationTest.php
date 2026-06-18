<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('counts reports overall and by status', function (): void {
    $post = PostTestModel::create();
    Report::factory()->against($post)->pending()->create();
    Report::factory()->against($post)->resolved()->create();

    expect($post->reportsCount())->toBe(2)
        ->and($post->reportsCount(Status::Pending))->toBe(1)
        ->and($post->reportsCount(Status::Resolved))->toBe(1);
});

it('reports whether a subject has been reported', function (): void {
    $post = PostTestModel::create();
    $reported = PostTestModel::create();
    Report::factory()->against($reported)->create();

    expect($post->hasBeenReported())->toBeFalse()
        ->and($reported->hasBeenReported())->toBeTrue();
});

it('reports whether a subject was reported by a given reporter', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();
    $other = UserTestModel::create();
    Report::factory()->against($post)->forReporter($user)->create();

    expect($post->isReportedBy($user))->toBeTrue()
        ->and($post->isReportedBy($other))->toBeFalse();
});

it('exposes the pending reports relation', function (): void {
    $post = PostTestModel::create();
    Report::factory()->against($post)->pending()->create();
    Report::factory()->against($post)->resolved()->create();

    expect($post->pendingReports()->count())->toBe(1);
});

it('eager loads report counts via scope', function (): void {
    $post = PostTestModel::create();
    Report::factory()->against($post)->count(2)->create();

    $loaded = PostTestModel::query()->withReportCounts()->find($post->getKey());

    expect($loaded?->getAttribute('reports_count'))->toBe(2);
});

it('orders by most reported and respects a status filter', function (): void {
    $a = PostTestModel::create();
    $b = PostTestModel::create();
    $c = PostTestModel::create();

    Report::factory()->against($a)->pending()->count(1)->create();
    Report::factory()->against($b)->pending()->count(3)->create();
    Report::factory()->against($c)->pending()->count(2)->create();
    // Resolved reports on A should not count when filtering to pending.
    Report::factory()->against($a)->resolved()->count(5)->create();

    $allOrder = PostTestModel::query()->mostReported()->pluck('id')->all();
    expect($allOrder[0])->toBe($a->getKey()); // 6 total

    $pendingOrder = PostTestModel::query()->mostReported(Status::Pending)->pluck('id')->all();
    expect($pendingOrder)->toBe([$b->getKey(), $c->getKey(), $a->getKey()]);
});

it('filters subjects reported more than a threshold', function (): void {
    $a = PostTestModel::create();
    $b = PostTestModel::create();
    Report::factory()->against($a)->pending()->count(2)->create();
    Report::factory()->against($b)->pending()->count(5)->create();

    $ids = PostTestModel::query()->reportedMoreThan(3)->pluck('id')->all();

    expect($ids)->toBe([$b->getKey()]);
});

it('filters subjects reported more than a threshold within a status', function (): void {
    $a = PostTestModel::create();
    Report::factory()->against($a)->resolved()->count(5)->create();
    Report::factory()->against($a)->pending()->count(1)->create();

    $ids = PostTestModel::query()->reportedMoreThan(3, Status::Pending)->pluck('id')->all();

    expect($ids)->toBe([]);
});
