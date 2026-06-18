<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    $this->post = PostTestModel::create();
    $this->user = UserTestModel::create();
});

it('creates a report through the fluent facade', function (): void {
    $report = Reports::report($this->post)
        ->by($this->user)
        ->for(Reason::Abuse)
        ->because('It violates the guidelines.')
        ->create();

    expect($report)->toBeInstanceOf(Report::class)
        ->and($report->reason)->toBe('abuse')
        ->and($report->description)->toBe('It violates the guidelines.')
        ->and($report->reporter_id)->toBe($this->user->getKey());
});

it('starts a fluent build from a reporter', function (): void {
    $report = Reports::from($this->user)->about($this->post)->for('spam')->create();

    expect($report->reporter_id)->toBe($this->user->getKey())
        ->and($report->reason)->toBe('spam');
});

it('creates a guest report', function (): void {
    $report = Reports::report($this->post)
        ->asGuest('guest-hash')
        ->reason('spam')
        ->because('Obvious spam.')
        ->create();

    expect($report->reporter_id)->toBeNull()
        ->and($report->guest_identifier)->toBe('guest-hash');
});

it('defaults to the configured reason when none is given', function (): void {
    $report = Reports::report($this->post)->by($this->user)->create();

    expect($report->reason)->toBe('other');
});

it('resolves a report through the facade', function (): void {
    $report = Reports::report($this->post)->by($this->user)->for('spam')->create();

    $resolved = Reports::resolve($report, $this->user, 'Handled.');

    expect($resolved->status)->toBe(Status::Resolved)
        ->and($resolved->resolution_note)->toBe('Handled.');
});

it('rejects a report through the facade', function (): void {
    $report = Reports::report($this->post)->by($this->user)->for('spam')->create();

    $rejected = Reports::reject($report, $this->user, 'Not a violation.');

    expect($rejected->status)->toBe(Status::Rejected);
});

it('exposes the allowed reasons', function (): void {
    expect(Reports::reasons())->toContain('spam', 'abuse');
});

it('throws when creating without a subject', function (): void {
    Reports::from($this->user)->for('spam')->create();
})->throws(LogicException::class);
