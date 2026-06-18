<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportCreated;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('uses the configured table name', function (): void {
    config()->set('reports.table', 'reports');

    expect((new Report)->getTable())->toBe('reports');
});

it('casts status to the Status enum', function (): void {
    $report = Report::factory()->create();

    expect($report->status)->toBe(Status::Pending);
});

it('dispatches ReportCreated when a report is created', function (): void {
    Event::fake();

    $report = Report::factory()->create();

    Event::assertDispatched(fn (ReportCreated $event): bool => $event->report->is($report));
});

it('changes status through an allowed transition and fires the event', function (): void {
    Event::fake([ReportStatusChanged::class]);
    $report = Report::factory()->pending()->create();

    $report->changeStatusTo(Status::InReview);

    expect($report->status)->toBe(Status::InReview);
    Event::assertDispatched(fn (ReportStatusChanged $event): bool => $event->statusBefore === Status::Pending
        && $event->statusNow === Status::InReview);
});

it('throws on a disallowed transition under strict mode', function (): void {
    $report = Report::factory()->resolved()->create();

    $report->changeStatusTo(Status::InReview);
})->throws(InvalidStatusTransitionException::class);

it('allows any transition when strict mode is disabled', function (): void {
    config()->set('reports.strict_transitions', false);
    $report = Report::factory()->resolved()->create();

    $report->changeStatusTo(Status::InReview);

    expect($report->status)->toBe(Status::InReview);
});

it('is a no-op when changing to the current status', function (): void {
    Event::fake([ReportStatusChanged::class]);
    $report = Report::factory()->pending()->create();

    expect($report->changeStatusTo(Status::Pending))->toBe($report);
    Event::assertNotDispatched(ReportStatusChanged::class);
});

it('soft deletes a report', function (): void {
    $report = Report::factory()->create();

    $report->delete();

    expect($report->trashed())->toBeTrue()
        ->and(Report::query()->find($report->getKey()))->toBeNull()
        ->and(Report::withTrashed()->find($report->getKey()))->not->toBeNull();
});

it('resolves the resolvedBy relation', function (): void {
    $admin = UserTestModel::create();
    $report = Report::factory()->resolved($admin)->create();

    $resolver = $report->resolvedBy;

    expect($resolver)->toBeInstanceOf(UserTestModel::class)
        ->and($resolver?->getKey())->toBe($admin->getKey());
});

it('filters reports through status and reason scopes', function (): void {
    Report::factory()->pending()->reason('spam')->create();
    Report::factory()->resolved()->reason('abuse')->create();
    Report::factory()->rejected()->reason('spam')->create();

    expect(Report::query()->pending()->count())->toBe(1)
        ->and(Report::query()->resolved()->count())->toBe(1)
        ->and(Report::query()->rejected()->count())->toBe(1)
        ->and(Report::query()->open()->count())->toBe(1)
        ->and(Report::query()->withStatus(Status::Resolved)->count())->toBe(1)
        ->and(Report::query()->withReason('spam')->count())->toBe(2);
});
