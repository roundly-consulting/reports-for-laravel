<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\Models\Report;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('fails when no days option or config is set', function (): void {
    config()->set('reports.prune_after_days', null);

    $this->artisan('reports:prune')
        ->assertExitCode(1);
});

it('soft-deletes terminal reports older than the cutoff', function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');

    $old = Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDays(40)]);
    $recent = Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDays(5)]);
    $openOld = Report::factory()->pending()->create(['created_at' => Carbon::now()->subDays(40)]);

    $this->artisan('reports:prune', ['--days' => 30])
        ->assertExitCode(0);

    expect(Report::query()->find($old->getKey()))->toBeNull()
        ->and(Report::withTrashed()->find($old->getKey()))->not->toBeNull()
        ->and(Report::query()->find($recent->getKey()))->not->toBeNull()
        ->and(Report::query()->find($openOld->getKey()))->not->toBeNull();
});

it('permanently deletes with the force flag', function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');
    $old = Report::factory()->rejected()->create(['created_at' => Carbon::now()->subDays(40)]);

    $this->artisan('reports:prune', ['--days' => 30, '--force' => true])
        ->assertExitCode(0);

    expect(Report::withTrashed()->find($old->getKey()))->toBeNull();
});

it('uses the configured default days', function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');
    config()->set('reports.prune_after_days', 10);
    $old = Report::factory()->closed()->create(['created_at' => Carbon::now()->subDays(20)]);

    $this->artisan('reports:prune')->assertExitCode(0);

    expect(Report::query()->find($old->getKey()))->toBeNull();
});

it('prunes nothing when there is nothing old enough', function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');
    Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDays(1)]);

    $this->artisan('reports:prune', ['--days' => 30])->assertExitCode(0);

    expect(Report::query()->count())->toBe(1);
});
