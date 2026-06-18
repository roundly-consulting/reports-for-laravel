<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('creates report for given model', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $report = $user->giveReportTo(
        model: $post,
        description: 'This is a very useful report description.',
        type: 'Custom',
    );

    expect($report)->toBeInstanceOf(Report::class)
        ->and($report->reported_id)->toBe($post->getKey())
        ->and($report->reported_type)->toBe($post->getMorphClass())
        ->and($report->reporter_id)->toBe($user->getKey())
        ->and($report->reporter_type)->toBe($user->getMorphClass())
        ->and($report->description)->toBe('This is a very useful report description.')
        ->and($report->type)->toBe('Custom');
});

it('defaults the report type to "default"', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $report = $user->giveReportTo(
        model: $post,
        description: 'No type supplied.',
    );

    expect($report->type)->toBe('default');
});

it('returns reports given by entity', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $report = $user->giveReportTo(
        model: $post,
        description: 'This is a very useful report description.',
        type: 'Custom',
    );

    $given = $user->givenReports;
    $first = $given->first();

    expect($given)->toHaveCount(1)
        ->and($first)->toBeInstanceOf(Report::class)
        ->and($first?->getKey())->toBe($report->getKey())
        ->and($first?->description)->toBe('This is a very useful report description.')
        ->and($first?->type)->toBe('Custom');
});
