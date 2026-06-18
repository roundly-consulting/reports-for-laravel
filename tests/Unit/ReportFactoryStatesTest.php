<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('builds a default pending report with a real reason', function (): void {
    $report = Report::factory()->create();

    expect($report->status)->toBe(Status::Pending)
        ->and($report->reason)->toBe('spam')
        ->and($report->reporter_id)->toBeNull();
});

it('attaches a real reporter and subject', function (): void {
    $user = UserTestModel::create();
    $post = PostTestModel::create();

    $report = Report::factory()->forReporter($user)->against($post)->create();

    expect($report->reporter_id)->toBe($user->getKey())
        ->and($report->reporter_type)->toBe($user->getMorphClass())
        ->and($report->reported_id)->toBe($post->getKey())
        ->and($report->reported_type)->toBe($post->getMorphClass());
});

it('sets a reason from a string or enum', function (): void {
    expect(Report::factory()->reason('abuse')->create()->reason)->toBe('abuse')
        ->and(Report::factory()->reason(Reason::Harassment)->create()->reason)->toBe('harassment');
});

it('builds in-review reports', function (): void {
    expect(Report::factory()->inReview()->create()->status)->toBe(Status::InReview);
});

it('builds resolved reports with a resolver', function (): void {
    $admin = UserTestModel::create();
    $report = Report::factory()->resolved($admin)->create();

    expect($report->status)->toBe(Status::Resolved)
        ->and($report->resolved_by_id)->toBe($admin->getKey())
        ->and($report->resolved_at)->not->toBeNull();
});

it('builds rejected reports', function (): void {
    expect(Report::factory()->rejected()->create()->status)->toBe(Status::Rejected);
});

it('builds closed reports', function (): void {
    expect(Report::factory()->closed()->create()->status)->toBe(Status::Closed);
});

it('builds guest reports with an identifier', function (): void {
    $report = Report::factory()->guest('guest-hash')->create();

    expect($report->reporter_id)->toBeNull()
        ->and($report->guest_identifier)->toBe('guest-hash');

    $auto = Report::factory()->guest()->create();
    expect($auto->guest_identifier)->not->toBeNull();
});
