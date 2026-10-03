<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\MissingPruneWindowException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

/**
 * Every env value is a string. The numeric keys used to require a PHP int (`is_int`),
 * so `REPORTS_THRESHOLD=2` silently disabled the threshold and a string
 * `prune_after_days` was ignored; the switches used a `(bool)` cast, which reads the
 * string 'false' as true. They now coerce integer strings and boolean words; an empty
 * value is not a number and throws.
 */
afterEach(function (): void {
    Carbon::setTestNow();
});

function fileReports(PostTestModel $post, int $times): void
{
    foreach (range(1, $times) as $ignored) {
        Reports::report($post)->by(UserTestModel::create())->create();
    }
}

it('fires the threshold for an integer string', function (): void {
    config()->set('reports.threshold', '2');
    Event::fake([ReportThresholdReached::class]);
    $post = PostTestModel::create();

    fileReports($post, 3);

    Event::assertDispatchedTimes(ReportThresholdReached::class, 1);
    Event::assertDispatched(ReportThresholdReached::class, fn (ReportThresholdReached $event): bool => $event->count === 2 && $event->threshold === 2);
});

it('treats a zero or absent threshold as disabled', function (mixed $value): void {
    config()->set('reports.threshold', $value);
    Event::fake([ReportThresholdReached::class]);

    fileReports(PostTestModel::create(), 2);

    Event::assertNotDispatched(ReportThresholdReached::class);
})->with(['zero string' => '0', 'zero' => 0, 'null' => null]);

it('refuses a threshold that is not an integer before writing anything', function (mixed $value): void {
    config()->set('reports.threshold', $value);

    expect(fn () => Reports::report(PostTestModel::create())->by(UserTestModel::create())->create())
        ->toThrow(InvalidConfigurationException::class);

    expect(Report::query()->count())->toBe(0);
})->with(['word' => 'two', 'decimal' => '1.5', 'negative' => '-1', 'empty string (strict config)' => '']);

it('prunes with an integer-string window', function (): void {
    Carbon::setTestNow('2026-09-28 20:00:00');
    config()->set('reports.prune_after_days', '10');
    Report::factory()->closed()->create(['created_at' => Carbon::now()->subDays(20)]);

    expect(Reports::prune())->toBe(1);
});

it('treats an absent prune window as unset', function (): void {
    config()->set('reports.prune_after_days', null);

    Reports::prune();
})->throws(MissingPruneWindowException::class);

it('refuses an empty prune window instead of treating it as unset (strict config)', function (): void {
    config()->set('reports.prune_after_days', '');

    Reports::prune();
})->throws(InvalidConfigurationException::class, "Configuration value [reports.prune_after_days] must be an integer, [''] given.");

it('refuses a prune window that is not an integer', function (): void {
    config()->set('reports.prune_after_days', 'thirty');

    Reports::prune();
})->throws(InvalidConfigurationException::class);

it('reads the default quorum from an integer string', function (): void {
    config()->set('reports.moderation.default_quorum', '1');
    $report = Report::factory()->pending()->create();

    $request = Reports::moderate($report)
        ->requiring([UserTestModel::create(), UserTestModel::create()])
        ->rule(ApprovalRule::Quorum)
        ->open();

    expect($request->quorum)->toBe(1);
});

it('reads the string false as false for the switches', function (): void {
    config()->set('reports.strict_transitions', 'false');
    config()->set('reports.prevent_duplicates', 'false');
    config()->set('reports.allow_unknown_reasons', 'false');

    $post = PostTestModel::create();
    $user = UserTestModel::create();

    // prevent_duplicates=false: a second report from the same reporter is filed.
    Reports::report($post)->by($user)->create();
    $second = Reports::report($post)->by($user)->create();

    // strict_transitions=false: a jump the graph does not allow is applied.
    Reports::close($second);

    expect($second->fresh()?->status)->toBe(Status::Closed)
        // allow_unknown_reasons=false: an unknown slug is refused.
        ->and(Reports::allowsReason('copyright'))->toBeFalse();
});

it('reads the string true as true for the switches', function (): void {
    config()->set('reports.prevent_duplicates', 'true');
    config()->set('reports.allow_unknown_reasons', 'on');

    $post = PostTestModel::create();
    $user = UserTestModel::create();
    Reports::report($post)->by($user)->for('copyright')->create();

    expect(fn () => Reports::report($post)->by($user)->create())
        ->toThrow(DuplicateReportException::class);
});

it('renders string config in the about section, and flags an invalid one', function (): void {
    config()->set('reports.threshold', '5');
    config()->set('reports.prune_after_days', '30');
    config()->set('reports.moderation.default_quorum', '2');
    config()->set('reports.strict_transitions', 'false');
    config()->set('reports.prevent_duplicates', 'no');

    Artisan::call('about', ['--only' => 'reports']);
    $output = Artisan::output();

    expect($output)->toContain('5 open report(s)')
        ->toContain('30 day(s)')
        ->toContain('quorum 2')
        ->toMatch('/Strict transitions\s*\.+\s*OFF/')
        ->toMatch('/Duplicate prevention\s*\.+\s*OFF/');

    config()->set('reports.threshold', 'lots');
    Artisan::call('about', ['--only' => 'reports']);

    expect(Artisan::output())->toMatch('/Threshold\s*\.+\s*INVALID/');
});

it('refuses a prune --days that is not a non-negative integer', function (string $days): void {
    Carbon::setTestNow('2026-09-28 20:00:00');
    Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDay()]);

    $this->artisan('reports:prune', ['--days' => $days])->assertExitCode(1);

    expect(Report::query()->count())->toBe(1);
})->with(['word' => 'abc', 'negative' => '-5', 'decimal' => '1.5']);

it('throws on a switch typo instead of reading it as the default (strict config)', function (string $key, Closure $read): void {
    config()->set($key, 'disabled');

    expect($read)->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.",
    );
})->with([
    'prevent_duplicates' => ['reports.prevent_duplicates', fn () => Reports::report(PostTestModel::create())->by(UserTestModel::create())->create()],
    'strict_transitions' => ['reports.strict_transitions', fn () => Reports::close(Reports::report(PostTestModel::create())->by(UserTestModel::create())->create())],
    'allow_unknown_reasons' => ['reports.allow_unknown_reasons', fn (): bool => Reports::allowsReason('copyright')],
]);
