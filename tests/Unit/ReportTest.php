<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportCreated;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Models\Report;

it('casts status to the Status enum', function (): void {
    $report = Report::factory()->create();

    expect($report->status)->toBe(Status::New);
});

it('dispatches ReportCreated when a report is created', function (): void {
    Event::fake();

    $report = Report::factory()->create();

    Event::assertDispatched(
        fn (ReportCreated $event): bool => $event->report->is($report),
    );
});

it('changes the status of a report', function (): void {
    $report = Report::factory()->create();

    $report->changeStatusTo(Status::Solving);

    expect($report->status)->toBe(Status::Solving);
});

it('dispatches ReportStatusChanged after changing status', function (): void {
    $report = Report::factory()->create();

    Event::fake();

    $report->changeStatusTo(Status::Solving);

    Event::assertDispatched(
        fn (ReportStatusChanged $event): bool => $event->report->is($report)
            && $event->statusBefore === Status::New
            && $event->statusNow === Status::Solving,
    );
});

it('does not dispatch ReportStatusChanged when the status is unchanged', function (): void {
    $report = Report::factory()->create();

    Event::fake();

    $report->changeStatusTo(Status::New);

    expect($report->status)->toBe(Status::New);

    Event::assertNotDispatched(ReportStatusChanged::class);
});

it('returns the same instance when changing to the current status', function (): void {
    $report = Report::factory()->create();

    expect($report->changeStatusTo(Status::New))->toBe($report);
});

it('soft deletes a report', function (): void {
    $report = Report::factory()->create();

    $report->delete();

    expect($report->trashed())->toBeTrue()
        ->and(Report::query()->find($report->getKey()))->toBeNull()
        ->and(Report::withTrashed()->find($report->getKey()))->not->toBeNull();
});
