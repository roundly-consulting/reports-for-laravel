<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Actions\ResolveReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    $this->action = app(ResolveReportAction::class);
});

it('records the resolver, note and timestamp and fires ReportResolved', function (): void {
    Carbon::setTestNow('2026-06-18 20:30:00');
    Event::fake([ReportResolved::class]);

    $admin = UserTestModel::create();
    $report = Report::factory()->pending()->create();

    $resolved = $this->action->execute($report, new ResolveReportData(resolver: $admin, note: 'Removed.'));

    expect($resolved->status)->toBe(Status::Resolved)
        ->and($resolved->resolution_note)->toBe('Removed.')
        ->and($resolved->resolved_by_id)->toBe($admin->getKey())
        ->and($resolved->resolved_by_type)->toBe($admin->getMorphClass())
        ->and($resolved->resolved_at?->toDateTimeString())->toBe('2026-06-18 20:30:00');

    Event::assertDispatched(ReportResolved::class);

    Carbon::setTestNow();
});

it('resolves without a resolver for a system action', function (): void {
    $report = Report::factory()->pending()->create();

    $resolved = $this->action->execute($report, new ResolveReportData);

    expect($resolved->status)->toBe(Status::Resolved)
        ->and($resolved->resolved_by_id)->toBeNull();
});

it('throws when resolving from a non-resolvable status under strict transitions', function (): void {
    $report = Report::factory()->rejected()->create();

    $this->action->execute($report, new ResolveReportData);
})->throws(InvalidStatusTransitionException::class);
