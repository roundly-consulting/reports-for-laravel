<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * resolve()/reject() used to stamp the resolver, note and timestamp BEFORE checking the
 * move: resolving a rejected report threw but kept the new resolver and note, and
 * resolving twice restamped the resolver and fired ReportResolved again. The move is now
 * checked against the row re-read under a lock, and the status and stamps are written
 * together in one transaction.
 */
beforeEach(function (): void {
    $this->admin = UserTestModel::create();
    $this->other = UserTestModel::create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * The stored (raw) status and resolution of the report.
 *
 * @return array<string, mixed>
 */
function resolutionOf(Report $report): array
{
    return Arr::only(
        $report->fresh()?->getAttributes() ?? [],
        ['status', 'resolved_by_id', 'resolved_by_type', 'resolution_note', 'resolved_at'],
    );
}

it('writes nothing when resolving a rejected report throws', function (): void {
    $report = Report::factory()->pending()->create();
    Reports::reject($report, $this->admin, 'Not a violation.');
    $before = resolutionOf($report);

    expect(fn () => Reports::resolve($report, $this->other, 'OVERWRITTEN'))
        ->toThrow(InvalidStatusTransitionException::class);

    expect(resolutionOf($report))->toBe($before)
        ->and($report->resolution_note)->toBe('Not a violation.');
});

it('writes nothing when rejecting a resolved report throws', function (): void {
    $report = Report::factory()->pending()->create();
    Reports::resolve($report, $this->admin, 'Removed.');
    $before = resolutionOf($report);

    expect(fn () => Reports::reject($report, $this->other, 'OVERWRITTEN'))
        ->toThrow(InvalidStatusTransitionException::class);

    expect(resolutionOf($report))->toBe($before);
});

it('keeps the first decision and fires ReportResolved once when resolved twice', function (): void {
    $report = Report::factory()->pending()->create();
    Event::fake([ReportResolved::class, ReportStatusChanged::class]);

    Reports::resolve($report, $this->admin, 'first');
    Reports::resolve($report, $this->other, 'second');

    $fresh = $report->fresh();

    expect($fresh?->status)->toBe(Status::Resolved)
        ->and($fresh?->resolved_by_id)->toBe($this->admin->getKey())
        ->and($fresh?->resolution_note)->toBe('first');

    Event::assertDispatchedTimes(ReportResolved::class, 1);
    Event::assertDispatchedTimes(ReportStatusChanged::class, 1);
});

it('keeps the first rejection and fires ReportRejected once when rejected twice', function (): void {
    $report = Report::factory()->pending()->create();
    Event::fake([ReportRejected::class]);

    Reports::reject($report, $this->admin, 'first');
    Reports::reject($report, $this->other, 'second');

    expect($report->fresh()?->resolution_note)->toBe('first');
    Event::assertDispatchedTimes(ReportRejected::class, 1);
});

it('decides from the stored status, not a stale copy (lost race resolve then resolve)', function (): void {
    $report = Report::factory()->pending()->create();
    $stale = Report::query()->findOrFail($report->getKey());
    Event::fake([ReportResolved::class]);

    // Two moderators loaded the report while it was pending; the first one wins.
    Reports::resolve($report, $this->admin, 'winner');
    $returned = Reports::resolve($stale, $this->other, 'loser');

    expect($returned->status)->toBe(Status::Resolved)
        ->and($returned->resolved_by_id)->toBe($this->admin->getKey())
        ->and($returned->resolution_note)->toBe('winner')
        ->and($report->fresh()?->resolution_note)->toBe('winner');

    Event::assertDispatchedTimes(ReportResolved::class, 1);
});

it('decides from the stored status, not a stale copy (lost race resolve then reject)', function (): void {
    $report = Report::factory()->pending()->create();
    $stale = Report::query()->findOrFail($report->getKey());
    Event::fake([ReportRejected::class]);

    Reports::resolve($report, $this->admin, 'winner');

    expect(fn () => Reports::reject($stale, $this->other, 'loser'))
        ->toThrow(InvalidStatusTransitionException::class);

    expect($report->fresh()?->status)->toBe(Status::Resolved)
        ->and($report->fresh()?->resolution_note)->toBe('winner');

    Event::assertNotDispatched(ReportRejected::class);
});

it('clears the resolution when a settled report is reopened', function (Status $settled): void {
    config()->set('reports.strict_transitions', false);
    $report = Report::factory()->pending()->create();
    Reports::resolve($report, $this->admin, 'done');
    Reports::changeStatus($report, $settled);

    Reports::changeStatus($report, Status::Pending);

    $fresh = $report->fresh();

    expect($fresh?->status)->toBe(Status::Pending)
        ->and($fresh?->resolved_by_id)->toBeNull()
        ->and($fresh?->resolved_by_type)->toBeNull()
        ->and($fresh?->resolution_note)->toBeNull()
        ->and($fresh?->resolved_at)->toBeNull()
        ->and($report->resolved_at)->toBeNull()
        ->and(Report::query()->pending()->whereNotNull('resolved_at')->count())->toBe(0);
})->with([Status::Resolved, Status::Rejected, Status::Closed]);

it('clears the resolution when a settled report goes back into review', function (): void {
    config()->set('reports.strict_transitions', false);
    $report = Report::factory()->pending()->create();
    Reports::resolve($report, $this->admin, 'done');

    Reports::review($report);

    expect($report->fresh()?->resolved_at)->toBeNull()
        ->and($report->fresh()?->resolution_note)->toBeNull();
});

it('keeps the resolution when a resolved report is closed', function (): void {
    Carbon::setTestNow('2026-09-28 20:00:00');
    $report = Report::factory()->pending()->create();
    Reports::resolve($report, $this->admin, 'done');

    Reports::close($report);

    $fresh = $report->fresh();

    expect($fresh?->status)->toBe(Status::Closed)
        ->and($fresh?->resolved_by_id)->toBe($this->admin->getKey())
        ->and($fresh?->resolution_note)->toBe('done')
        ->and($fresh?->resolved_at?->toDateTimeString())->toBe('2026-09-28 20:00:00');
});

it('resolves a report again after it was reopened', function (): void {
    $report = Report::factory()->pending()->create();
    Reports::resolve($report, $this->admin, 'first');
    Reports::changeStatus($report, Status::Pending);

    Reports::resolve($report, $this->other, 'second');

    expect($report->fresh()?->resolved_by_id)->toBe($this->other->getKey())
        ->and($report->fresh()?->resolution_note)->toBe('second');
});

/**
 * The real-engine half of the race guard: a second connection holds the report row's
 * lock, so resolve() must wait for it (here: give up after the lock timeout) on the
 * locked read it decides from — not only on its final write, which would already have
 * decided from a stale status. SQLite has no row locks — it serializes writers instead.
 */
it('waits for the report row lock before deciding on postgres', function (): void {
    $report = Report::factory()->pending()->create();

    config()->set('database.connections.rival', config('database.connections.'.config('database.default')));
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table($report->getTable())->where('id', $report->getKey())->lockForUpdate()->first();

    DB::statement("set lock_timeout = '300ms'");

    try {
        Reports::resolve($report, $this->admin, 'blocked');
        $this->fail('resolve() did not wait for the row lock.');
    } catch (QueryException $e) {
        // It gave up on the locked READ it decides from, not on its final write.
        expect(strtolower($e->getSql()))->toStartWith('select')->toContain('for update');
    } finally {
        $rival->rollBack();
        DB::purge('rival');
    }

    expect($report->fresh()?->status)->toBe(Status::Pending)
        ->and($report->fresh()?->resolution_note)->toBeNull();
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql', 'row locks need a real engine');
