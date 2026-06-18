<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('returns collection of reports for a reported entity', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $report = Report::create([
        'reported_id' => $post->getKey(),
        'reported_type' => $post->getMorphClass(),
        'reporter_id' => $user->getKey(),
        'reporter_type' => $user->getMorphClass(),
        'type' => 'Custom',
        'description' => 'This post really triggers me.',
    ]);

    $reports = $post->reports;
    $first = $reports->first();

    expect($reports)->toHaveCount(1)
        ->and($first)->toBeInstanceOf(Report::class)
        ->and($first?->type)->toBe('Custom')
        ->and($first?->description)->toBe('This post really triggers me.')
        ->and($first?->getKey())->toBe($report->getKey());
});

it('orders posts by most reported first', function (): void {
    $post = PostTestModel::create();
    $anotherPost = PostTestModel::create();
    $andAnotherPost = PostTestModel::create();
    $user = UserTestModel::create();

    $reportPost = function (PostTestModel $post) use ($user): void {
        Report::create([
            'reported_id' => $post->getKey(),
            'reported_type' => $post->getMorphClass(),
            'reporter_id' => $user->getKey(),
            'reporter_type' => $user->getMorphClass(),
            'description' => 'This post really triggers me.',
        ]);
    };

    // 3 reports for anotherPost, 2 for andAnotherPost, 1 for post.
    $reportPost($post);
    $reportPost($anotherPost);
    $reportPost($anotherPost);
    $reportPost($anotherPost);
    $reportPost($andAnotherPost);
    $reportPost($andAnotherPost);

    $ordered = PostTestModel::query()->mostReported()->pluck('id')->all();

    expect($ordered)->toBe([
        $anotherPost->getKey(),
        $andAnotherPost->getKey(),
        $post->getKey(),
    ]);
});
