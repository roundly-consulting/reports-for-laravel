<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Actions\ChangeReportStatusAction;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;

beforeEach(function (): void {
    $this->action = app(ChangeReportStatusAction::class);
});

it('delegates an allowed transition to the model', function (): void {
    $report = Report::factory()->pending()->create();

    $this->action->execute($report, Status::InReview);

    expect($report->status)->toBe(Status::InReview);
});

it('throws on a disallowed transition under strict mode', function (): void {
    $report = Report::factory()->resolved()->create();

    $this->action->execute($report, Status::InReview);
})->throws(InvalidStatusTransitionException::class);

it('permits any transition when strict mode is off', function (): void {
    config()->set('reports.strict_transitions', false);
    $report = Report::factory()->resolved()->create();

    $this->action->execute($report, Status::InReview);

    expect($report->status)->toBe(Status::InReview);
});
