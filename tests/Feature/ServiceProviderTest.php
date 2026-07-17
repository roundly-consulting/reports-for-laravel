<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Reports\ReportsServiceProvider;
use RoundlyConsulting\Reports\Support\ReasonRegistry;
use RoundlyConsulting\Reports\Support\ReportsManager;

it('merges the package config', function (): void {
    expect(config('reports.table'))->toBe('reports')
        ->and(config('reports.key_type'))->toBe('bigint')
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

it('keeps the config publish tag and destination', function (): void {
    $published = ServiceProvider::pathsToPublish(ReportsServiceProvider::class, 'reports-config');

    expect($published)->toBe([
        realpath(__DIR__.'/../../config/reports.php') => config_path('reports.php'),
    ]);
});

it('registers the key-type blueprint macros', function (): void {
    expect(Blueprint::hasMacro('morphKey'))->toBeTrue()
        ->and(Blueprint::hasMacro('polymorphicSubject'))->toBeTrue()
        ->and(Blueprint::hasMacro('auditable'))->toBeTrue();
});

// The `about` section's three scenarios — the leak surface, every switched-off
// default, and the `any` duplicate scope — moved to AboutSectionTest.php, where they run
// through the shared secret-safe capture. It enforces the ordering these cases
// implemented by hand (non-empty output, then every mustRender string, and only then the
// secret scan) by construction rather than by the author having remembered it.
