<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
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

it('reports the package in about, without leaking moderation vocabulary', function (): void {
    config()->set('reports.reasons', ['insider-trading-tip', 'staff-misconduct']);
    config()->set('reports.default_reason', 'staff-misconduct');
    config()->set('reports.threshold', 5);

    Artisan::call('about', ['--only' => 'reports']);
    $output = Artisan::output();

    // Guards the guard: the section really did render.
    expect($output)->toContain('Key type')
        ->toContain('2 allowed')
        ->toContain('5 open report(s)')
        ->toContain('CUSTOM');

    // A reason slug is the host's moderation vocabulary — it must never render.
    expect($output)->not->toContain('insider-trading-tip')
        ->and($output)->not->toContain('staff-misconduct');
});

it('reports every switched-off setting in about', function (): void {
    config()->set('reports.prevent_duplicates', false);
    config()->set('reports.strict_transitions', false);
    config()->set('reports.prune_after_days', 30);
    config()->set('reports.moderation.default_quorum', 2);

    Artisan::call('about', ['--only' => 'reports']);
    $output = Artisan::output();

    expect($output)->toContain('30 day(s)')
        ->toContain('DISABLED')
        ->toContain('quorum 2');
});

it('reports the any duplicate scope in about', function (): void {
    config()->set('reports.duplicate_scope', 'any');

    Artisan::call('about', ['--only' => 'reports']);

    expect(Artisan::output())->toContain('scope any');
});
