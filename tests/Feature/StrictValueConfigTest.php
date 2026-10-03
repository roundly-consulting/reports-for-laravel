<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

/**
 * Sweep 2 — the non-boolean settings. A `duplicate_scope` typo deduped against every report
 * ever filed while `about` claimed `open`; an unknown moderation rule or quorum quietly fell
 * back to the strictest setting; a junk table name became `reports`. Each one now throws.
 *
 * Sweep 3 — a blank value (a host's `KEY=`, or whitespace) is not set: it takes the default,
 * exactly like an absent key. Junk still throws.
 */
it('refuses a duplicate scope typo instead of reading it as any (strict config)', function (mixed $value): void {
    config()->set('reports.duplicate_scope', $value);

    expect(fn () => Reports::report(PostTestModel::create())->by(UserTestModel::create())->create())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [reports.duplicate_scope] must be one of [open, any]');

    expect(Report::query()->count())->toBe(0);
})->with(['typo' => ['opne'], 'capitalised' => ['Open']]);

it('dedupes against open reports only when the scope is absent or blank (strict config)', function (?string $value): void {
    config()->set('reports.duplicate_scope', $value);
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $first = Reports::report($post)->by($user)->create();
    Reports::resolve($first);

    expect(Reports::report($post)->by($user)->create()->getKey())->not->toBe($first->getKey());
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('still dedupes against every report when the scope is any (strict config)', function (): void {
    config()->set('reports.duplicate_scope', 'any');
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    Reports::resolve(Reports::report($post)->by($user)->create());

    expect(fn () => Reports::report($post)->by($user)->create())->toThrow(DuplicateReportException::class);
});

it('refuses a non-string table name instead of using reports (strict config)', function (mixed $value): void {
    config()->set('reports.table', $value);

    expect(fn () => (new Report)->getTable())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [reports.table] must be a non-empty string');
})->with(['an int' => [5], 'an array' => [['reports']]]);

it('uses the reports table when the name is absent or blank (strict config)', function (?string $value): void {
    config()->set('reports.table', $value);

    expect((new Report)->getTable())->toBe('reports');
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => ['  ']]);

it('reads blank reasons as not set, so the enum slugs apply (strict config)', function (): void {
    config()->set('reports.reasons', '');
    config()->set('reports.default_reason', ' ');

    expect(Reports::allowsReason('spam'))->toBeTrue()
        ->and(Reports::report(PostTestModel::create())->by(UserTestModel::create())->create()->reason)->toBe('other');
});

it('flags a broken setting in about instead of rendering a fallback (strict config)', function (): void {
    config()->set('reports.duplicate_scope', 'opne');
    config()->set('reports.moderation.default_rule', 'garbage');
    config()->set('reports.table', 5);

    Artisan::call('about', ['--only' => 'reports']);
    $output = Artisan::output();

    expect($output)->toMatch('/Duplicate prevention\W+INVALID/')
        ->and($output)->toMatch('/Moderation\W+INVALID/')
        ->and($output)->toMatch('/Table\W+INVALID/')
        ->and($output)->not->toContain('scope open');
});

it('flags a junk default quorum in about (strict config)', function (): void {
    config()->set('reports.moderation.default_quorum', 'two');

    Artisan::call('about', ['--only' => 'reports']);

    expect(Artisan::output())->toMatch('/Moderation\W+INVALID/');
});
