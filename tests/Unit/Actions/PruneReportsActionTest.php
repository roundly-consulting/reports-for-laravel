<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\Actions\PruneReportsAction;
use RoundlyConsulting\Reports\Exceptions\MissingPruneWindowException;
use RoundlyConsulting\Reports\Exceptions\ReportsException;
use RoundlyConsulting\Reports\Models\Report;

beforeEach(function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');
    $this->action = app(PruneReportsAction::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('soft-deletes only terminal reports older than the window', function (): void {
    $resolved = Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDays(40)]);
    $rejected = Report::factory()->rejected()->create(['created_at' => Carbon::now()->subDays(40)]);
    $closed = Report::factory()->closed()->create(['created_at' => Carbon::now()->subDays(40)]);
    $open = Report::factory()->pending()->create(['created_at' => Carbon::now()->subDays(40)]);
    $recent = Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDays(5)]);

    expect($this->action->execute(30))->toBe(3)
        ->and(Report::query()->pluck('id')->all())->toEqualCanonicalizing([$open->id, $recent->id])
        ->and(Report::onlyTrashed()->pluck('id')->all())->toEqualCanonicalizing([$resolved->id, $rejected->id, $closed->id]);
});

it('deletes permanently with force', function (): void {
    $old = Report::factory()->rejected()->create(['created_at' => Carbon::now()->subDays(40)]);

    expect($this->action->execute(30, force: true))->toBe(1)
        ->and(Report::withTrashed()->find($old->getKey()))->toBeNull();
});

it('falls back to the configured window', function (): void {
    config()->set('reports.prune_after_days', 10);
    Report::factory()->closed()->create(['created_at' => Carbon::now()->subDays(20)]);

    expect($this->action->execute())->toBe(1);
});

it('throws a reports exception without a window', function (): void {
    config()->set('reports.prune_after_days', 'ten');

    expect(fn () => $this->action->execute())
        ->toThrow(MissingPruneWindowException::class, 'reports.prune_after_days is not configured')
        ->and(MissingPruneWindowException::make())->toBeInstanceOf(ReportsException::class);
});
