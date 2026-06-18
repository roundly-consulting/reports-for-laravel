<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;

it('walks the legal lifecycle pending to in-review to resolved', function (): void {
    Event::fake([ReportStatusChanged::class]);
    $report = Report::factory()->pending()->create();

    $report->changeStatusTo(Status::InReview);
    $report->changeStatusTo(Status::Resolved);

    expect($report->fresh()?->status)->toBe(Status::Resolved);
    Event::assertDispatchedTimes(ReportStatusChanged::class, 2);
});

it('rejects an illegal jump when strict', function (): void {
    $report = Report::factory()->resolved()->create();

    expect(fn () => $report->changeStatusTo(Status::InReview))
        ->toThrow(InvalidStatusTransitionException::class);
});

it('permits an illegal jump when not strict', function (): void {
    config()->set('reports.strict_transitions', false);
    $report = Report::factory()->resolved()->create();

    $report->changeStatusTo(Status::InReview);

    expect($report->status)->toBe(Status::InReview);
});
