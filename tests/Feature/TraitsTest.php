<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\Fixtures\MemberTestModel;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    $this->post = PostTestModel::create();
    $this->user = UserTestModel::create();
});

it('files a report via giveReportTo with a default reason', function (): void {
    $report = $this->user->giveReportTo($this->post, 'Please review.');

    expect($report)->toBeInstanceOf(Report::class)
        ->and($report->reason)->toBe('other')
        ->and($report->status)->toBe(Status::Pending)
        ->and($report->reporter_id)->toBe($this->user->getKey());
});

it('files a report via giveReportTo with an explicit reason enum', function (): void {
    $report = $this->user->giveReportTo($this->post, 'Bad.', Reason::Abuse);

    expect($report->reason)->toBe('abuse');
});

it('prevents duplicate reports through the trait', function (): void {
    $this->user->giveReportTo($this->post, 'First.', Reason::Spam);

    $this->user->giveReportTo($this->post, 'Second.', Reason::Spam);
})->throws(DuplicateReportException::class);

it('starts a fluent build from the reporter trait', function (): void {
    $report = $this->user->report($this->post)->for(Reason::Spam)->because('Spammy.')->create();

    expect($report->reporter_id)->toBe($this->user->getKey())
        ->and($report->reason)->toBe('spam');
});

it('resolves reporter and reported relations', function (): void {
    $report = $this->user->giveReportTo($this->post, 'Review me.');

    expect($report->reporter)->toBeInstanceOf(UserTestModel::class)
        ->and($report->reporter?->getKey())->toBe($this->user->getKey())
        ->and($report->reported)->toBeInstanceOf(PostTestModel::class)
        ->and($report->reported?->getKey())->toBe($this->post->getKey());
});

it('returns reports given by a reporter', function (): void {
    $this->user->giveReportTo($this->post, 'Review me.', Reason::Spam);

    expect($this->user->givenReports)->toHaveCount(1);
});

it('returns reports for a reported subject', function (): void {
    $this->user->giveReportTo($this->post, 'Review me.', Reason::Spam);

    expect($this->post->reports)->toHaveCount(1);
});

it('lets one model both file reports and be reported', function (): void {
    $alice = MemberTestModel::create();
    $bob = MemberTestModel::create();

    $report = $alice->giveReportTo($bob, 'Rude.', Reason::Harassment);
    $fluent = $bob->report($alice)->for(Reason::Spam)->create();

    expect($bob->reports()->sole()->is($report))->toBeTrue()
        ->and($alice->givenReports()->sole()->is($report))->toBeTrue()
        ->and($alice->reports()->sole()->is($fluent))->toBeTrue()
        ->and($bob->givenReports()->sole()->is($fluent))->toBeTrue()
        ->and($bob->isReportedBy($alice))->toBeTrue();
});
