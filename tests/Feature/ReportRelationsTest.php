<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('resolves the reporter and reported entities', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $report = $user->giveReportTo(
        model: $post,
        description: 'Please review this post.',
    );

    $reporter = $report->reporter;
    $reported = $report->reported;

    expect($reporter)->toBeInstanceOf(UserTestModel::class)
        ->and($reporter->getKey())->toBe($user->getKey())
        ->and($reported)->toBeInstanceOf(PostTestModel::class)
        ->and($reported->getKey())->toBe($post->getKey());
});

it('builds reports in a given status through the factory state', function (): void {
    $report = Report::factory()->status(Status::Closed)->create();

    expect($report->status)->toBe(Status::Closed);
});
