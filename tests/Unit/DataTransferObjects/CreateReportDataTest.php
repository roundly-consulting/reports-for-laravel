<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('normalises a Reason enum to its slug', function (): void {
    $post = new PostTestModel(['id' => 1]);

    $data = new CreateReportData(subject: $post, reason: Reason::Abuse);

    expect($data->reason)->toBe('abuse')
        ->and($data->subject)->toBe($post)
        ->and($data->reporter)->toBeNull()
        ->and($data->description)->toBeNull()
        ->and($data->guestIdentifier)->toBeNull();
});

it('keeps a string reason as-is and carries all fields', function (): void {
    $post = new PostTestModel(['id' => 1]);
    $user = new UserTestModel(['id' => 2]);

    $data = new CreateReportData(
        subject: $post,
        reason: 'copyright',
        reporter: $user,
        description: 'Mine.',
        guestIdentifier: 'hash',
    );

    expect($data->reason)->toBe('copyright')
        ->and($data->reporter)->toBe($user)
        ->and($data->description)->toBe('Mine.')
        ->and($data->guestIdentifier)->toBe('hash');
});
