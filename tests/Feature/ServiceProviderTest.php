<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportsManager;

it('merges the package config', function (): void {
    expect(config('reports.table'))->toBe('reports')
        ->and(config('reports.morph_key_type'))->toBe('bigint')
        ->and(config('reports.reasons'))->toContain('spam');
});

it('resolves the manager and registry as singletons', function (): void {
    expect(app(ReportsManager::class))->toBe(app(ReportsManager::class))
        ->and(app(ReasonRegistry::class))->toBe(app(ReasonRegistry::class));
});

it('registers the artisan commands', function (): void {
    $commands = array_keys(app(Kernel::class)->all());

    expect($commands)->toContain('reports:prune')
        ->and($commands)->toContain('reports:recount');
});

it('registers the moderation status-sync listener', function (): void {
    expect(app('events')->getListeners(ApprovalRequestResolved::class))
        ->not->toBeEmpty();
});
