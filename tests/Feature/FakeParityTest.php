<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Exceptions\MissingPruneWindowException;
use RoundlyConsulting\Reports\Exceptions\ModerationNotAllowedException;
use RoundlyConsulting\Reports\Exceptions\UnknownReportReasonException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Testing\ReportsFake;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

/**
 * The fake refuses exactly what the real manager refuses. Each scenario runs twice — on
 * the real manager and under `Reports::fake()` — and expects the same exception; under the
 * fake, a refused call records nothing. A fake that records what the real code throws on
 * lets a host's test pass over a path that fails in production.
 */
dataset('managers', [
    'real manager' => [false],
    'Reports::fake()' => [true],
]);

function parityFake(bool $fake): ?ReportsFake
{
    return $fake ? Reports::fake() : null;
}

beforeEach(function (): void {
    $this->post = PostTestModel::create();
    $this->user = UserTestModel::create();
});

it('refuses an unknown reason', function (bool $fake): void {
    $reports = parityFake($fake);

    expect(fn () => Reports::report($this->post)->by($this->user)->for('bogus')->create())
        ->toThrow(UnknownReportReasonException::class, 'Unknown report reason [bogus]');

    $reports?->assertNothingReported();
    expect(Report::query()->count())->toBe(0);
})->with('managers');

it('refuses an invalid threshold before filing', function (bool $fake): void {
    $reports = parityFake($fake);
    config()->set('reports.threshold', 'two');

    expect(fn () => Reports::report($this->post)->by($this->user)->create())
        ->toThrow(InvalidConfigurationException::class, 'reports.threshold');

    $reports?->assertNothingReported();
})->with('managers');

it('refuses a duplicate report by the same reporter', function (bool $fake): void {
    $reports = parityFake($fake);

    Reports::report($this->post)->by($this->user)->for(Reason::Spam)->create();

    expect(fn () => Reports::report($this->post)->by($this->user)->for(Reason::Abuse)->create())
        ->toThrow(DuplicateReportException::class)
        ->and(Report::query()->count())->toBe($fake ? 0 : 1);

    if ($reports instanceof ReportsFake) {
        $reports->assertReported($this->post, $this->user, Reason::Spam);

        expect(fn () => $reports->assertReported($this->post, reason: Reason::Abuse))
            ->toThrow(AssertionFailedError::class);
    }
})->with('managers');

it('refuses a duplicate of a stored report', function (bool $fake): void {
    Report::factory()->forReporter($this->user)->against($this->post)->pending()->create();
    $reports = parityFake($fake);

    expect(fn () => Reports::report($this->post)->by($this->user)->create())
        ->toThrow(DuplicateReportException::class);

    $reports?->assertNothingReported();
})->with('managers');

it('refuses a duplicate guest report', function (bool $fake): void {
    $reports = parityFake($fake);

    Reports::report($this->post)->asGuest('guest-hash')->create();

    expect(fn () => Reports::report($this->post)->asGuest('guest-hash')->create())
        ->toThrow(DuplicateReportException::class);

    // A different guest, and an anonymous filing without a dedupe key, still go through.
    Reports::report($this->post)->asGuest('other-guest')->create();
    Reports::report($this->post)->create();
    Reports::report($this->post)->create();

    expect(Report::query()->count())->toBe($fake ? 0 : 4);
})->with('managers');

it('lets a settled report be filed again under the open scope only', function (bool $fake, string $scope, bool $refused): void {
    config()->set('reports.duplicate_scope', $scope);
    $reports = parityFake($fake);

    $first = Reports::report($this->post)->by($this->user)->create();
    Reports::resolve($first);

    $again = fn () => Reports::report($this->post)->by($this->user)->create();

    if ($refused) {
        expect($again)->toThrow(DuplicateReportException::class);
    } else {
        expect($again())->toBeInstanceOf(Report::class);
    }

    $reports?->assertResolved($first);
})->with('managers')->with([
    'open scope' => ['open', false],
    'any scope' => ['any', true],
]);

it('files duplicates when prevention is off', function (bool $fake): void {
    config()->set('reports.prevent_duplicates', false);
    parityFake($fake);

    Reports::report($this->post)->by($this->user)->create();
    Reports::report($this->post)->by($this->user)->create();

    expect(Report::query()->count())->toBe($fake ? 0 : 2);
})->with('managers');

it('refuses moderation without moderators', function (bool $fake): void {
    $report = Report::factory()->pending()->create();
    $reports = parityFake($fake);

    expect(fn () => Reports::moderate($report)->requiring([])->open())
        ->toThrow(MissingModeratorsException::class);

    $reports?->assertNothingModerated();
    expect($report->approvalRequests()->count())->toBe(0);
})->with('managers');

it('refuses to prune without a window', function (bool $fake): void {
    config()->set('reports.prune_after_days', null);
    $reports = parityFake($fake);

    expect(fn () => Reports::prune())->toThrow(MissingPruneWindowException::class);

    $this->artisan('reports:prune')->assertExitCode(1);
    $this->artisan('reports:prune', ['--force' => true])->assertExitCode(1);

    $reports?->assertNothingPruned();
})->with('managers');

it('prunes on the configured window when no days are given', function (bool $fake): void {
    config()->set('reports.prune_after_days', 30);
    $reports = parityFake($fake);

    expect(Reports::prune())->toBe(0);

    $reports?->assertPruned();
})->with('managers');

it('refuses a status move the strict graph forbids', function (bool $fake): void {
    $report = Report::factory()->pending()->create();
    $reports = parityFake($fake);

    expect(fn () => Reports::close($report))
        ->toThrow(InvalidStatusTransitionException::class, 'from [pending] to [closed]');

    $reports?->assertNothingStatusChanged();
})->with('managers');

it('checks each move against where the last one left the report', function (bool $fake): void {
    $report = Report::factory()->pending()->create();
    $reports = parityFake($fake);

    Reports::review($report);
    Reports::resolve($report);
    Reports::resolve($report);

    // Resolved → Rejected is not in the graph; Resolved → Closed is.
    expect(fn () => Reports::reject($report))
        ->toThrow(InvalidStatusTransitionException::class, 'from [resolved] to [rejected]')
        ->and(fn () => Reports::changeStatus($report, Status::InReview))
        ->toThrow(InvalidStatusTransitionException::class, 'from [resolved] to [in_review]');

    Reports::close($report);

    $reports?->assertNothingRejected();
    $reports?->assertStatusChanged($report, Status::Closed);
})->with('managers');

it('checks a filed report, and a fresh copy of a stored one, against their last move', function (bool $fake): void {
    $reports = parityFake($fake);

    $filed = Reports::report($this->post)->by($this->user)->create();
    Reports::reject($filed);

    expect(fn () => Reports::resolve($filed))
        ->toThrow(InvalidStatusTransitionException::class, 'from [rejected] to [resolved]');

    $stored = Report::factory()->pending()->create();
    Reports::resolve($stored);

    expect(fn () => Reports::reject(Report::query()->findOrFail($stored->getKey())))
        ->toThrow(InvalidStatusTransitionException::class, 'from [resolved] to [rejected]');

    $reports?->assertRejected($filed);
    $reports?->assertResolved($stored);
})->with('managers');

it('allows any move when strict transitions are off', function (bool $fake): void {
    config()->set('reports.strict_transitions', false);
    $report = Report::factory()->pending()->create();
    $reports = parityFake($fake);

    Reports::close($report);
    Reports::reject($report);

    $reports?->assertStatusChanged($report, Status::Closed);
    $reports?->assertRejected($report);

    expect($report->fresh()?->status)->toBe($fake ? Status::Pending : Status::Rejected);
})->with('managers');

it('refuses to moderate a settled report', function (bool $fake): void {
    $report = Report::factory()->pending()->create();
    $reports = parityFake($fake);

    Reports::resolve($report);

    expect(fn () => Reports::moderate($report)->requiring([$this->user])->open())
        ->toThrow(ModerationNotAllowedException::class, 'is already settled [resolved]')
        ->and(fn () => Reports::moderate(Report::factory()->rejected()->create())->requiring([$this->user])->open())
        ->toThrow(ModerationNotAllowedException::class, 'is already settled [rejected]');

    $reports?->assertNothingModerated();
})->with('managers');

it('refuses a second moderation request while one is pending', function (bool $fake): void {
    $report = Report::factory()->pending()->create();
    $opened = Report::factory()->pending()->create();
    Reports::moderate($opened)->requiring([$this->user])->open();
    $reports = parityFake($fake);

    Reports::moderate($report)->requiring([$this->user])->open();

    expect(fn () => Reports::moderate($report)->requiring([$this->user])->open())
        ->toThrow(ModerationNotAllowedException::class, 'already has a pending moderation request')
        ->and(fn () => Reports::moderate($opened)->requiring([$this->user])->open())
        ->toThrow(ModerationNotAllowedException::class, 'already has a pending moderation request');

    expect($report->approvalRequests()->count())->toBe($fake ? 0 : 1)
        ->and($opened->approvalRequests()->count())->toBe(1);

    if ($reports instanceof ReportsFake) {
        $reports->assertModerated($report);

        expect(fn () => $reports->assertModerated($opened))->toThrow(AssertionFailedError::class);
    }
})->with('managers');
