<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;
use RoundlyConsulting\Reports\Testing\ReportsFake;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    $this->post = PostTestModel::create();
    $this->user = UserTestModel::create();
});

it('is a manager subtype installed behind the facade and the container', function (): void {
    $fake = Reports::fake();

    expect($fake)->toBeInstanceOf(ReportsManager::class)
        ->and(app(ReportsManager::class))->toBe($fake)
        ->and(Reports::getFacadeRoot())->toBe($fake)
        ->and(Reports::fake())->not->toBe($fake)
        ->and(Reports::fake())->toBeInstanceOf(ReportsFake::class);
});

it('records reports from the builders, the dto, the trait and an injected manager without writing', function (): void {
    $fake = Reports::fake();
    $other = PostTestModel::create();
    $third = PostTestModel::create();
    $member = UserTestModel::create();

    $built = Reports::report($this->post)->by($this->user)->for(Reason::Spam)->create();
    Reports::create(new CreateReportData(subject: $other, reason: 'abuse'));
    $this->user->giveReportTo($other, 'Rude.', Reason::Harassment);
    $member->report($this->post)->for('misinformation')->create();
    app(ReportsManager::class)->from($this->user)->about($third)->create();

    $fake->assertReported($this->post);
    $fake->assertReported($this->post, $this->user, Reason::Spam);
    $fake->assertReported($this->post, $member, 'misinformation');
    $fake->assertReported($other, reason: 'abuse');
    $fake->assertReported($other, $this->user, Reason::Harassment);
    $fake->assertReported($third, $this->user, 'other');

    expect($built->exists)->toBeFalse()
        ->and($built->status)->toBe(Status::Pending)
        ->and($built->reason)->toBe('spam')
        ->and(Report::query()->count())->toBe(0);
});

it('fails assertReported and assertNothingReported when they do not hold', function (): void {
    $fake = Reports::fake();

    $fake->assertNothingReported();

    expect(fn () => $fake->assertReported($this->post))
        ->toThrow(AssertionFailedError::class, 'Expected the subject to be reported.');

    Reports::report($this->post)->by($this->user)->for('spam')->create();

    expect(fn () => $fake->assertNothingReported())
        ->toThrow(AssertionFailedError::class, 'but 1 were')
        ->and(fn () => $fake->assertReported(PostTestModel::create()))
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertReported($this->post, UserTestModel::create()))
        ->toThrow(AssertionFailedError::class, 'by the given reporter')
        ->and(fn () => $fake->assertReported($this->post, reason: Reason::Abuse))
        ->toThrow(AssertionFailedError::class, 'for [abuse]');
});

it('fails assertReported by a reporter for a guest report', function (): void {
    $fake = Reports::fake();

    Reports::report($this->post)->asGuest('guest-hash')->create();

    $fake->assertReported($this->post);

    expect(fn () => $fake->assertReported($this->post, $this->user))
        ->toThrow(AssertionFailedError::class);
});

it('records resolve and reject with actor and note', function (): void {
    $fake = Reports::fake();
    $report = Report::factory()->pending()->create();
    $rejected = Report::factory()->pending()->create();

    Reports::resolve($report, $this->user, 'Handled.');
    Reports::reject($rejected, note: 'Not a violation.');

    $fake->assertResolved($report);
    $fake->assertResolved($report, $this->user, 'Handled.');
    $fake->assertRejected($rejected, note: 'Not a violation.');

    expect($report->fresh()?->status)->toBe(Status::Pending)
        ->and(fn () => $fake->assertResolved($report, UserTestModel::create()))
        ->toThrow(AssertionFailedError::class, 'by the given actor')
        ->and(fn () => $fake->assertResolved($report, note: 'Other.'))
        ->toThrow(AssertionFailedError::class, 'with note [Other.]')
        ->and(fn () => $fake->assertRejected($rejected, $this->user))
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertRejected(Report::factory()->pending()->create()))
        ->toThrow(AssertionFailedError::class, 'Expected the report to be rejected.')
        ->and(fn () => $fake->assertNothingResolved())
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingRejected())
        ->toThrow(AssertionFailedError::class);
});

it('asserts nothing was resolved or rejected', function (): void {
    $fake = Reports::fake();

    $fake->assertNothingResolved();
    $fake->assertNothingRejected();

    expect(fn () => $fake->assertResolved(Report::factory()->pending()->create()))
        ->toThrow(AssertionFailedError::class, 'Expected the report to be resolved.');
});

it('records status changes from the facade, review, close and the model method', function (): void {
    $fake = Reports::fake();
    $report = Report::factory()->pending()->create();
    $other = Report::factory()->pending()->create();

    Reports::review($report);
    Reports::changeStatus($report, Status::Resolved);
    Reports::close($report);
    $returned = $other->changeStatusTo(Status::Resolved);

    $fake->assertStatusChanged($report);
    $fake->assertStatusChanged($report, Status::InReview);
    $fake->assertStatusChanged($report, Status::Resolved);
    $fake->assertStatusChanged($report, Status::Closed);
    $fake->assertStatusChanged($other, Status::Resolved);

    expect($returned)->toBe($other)
        ->and($report->fresh()?->status)->toBe(Status::Pending)
        ->and(fn () => $fake->assertStatusChanged($other, Status::Closed))
        ->toThrow(AssertionFailedError::class, 'to change to [closed]')
        ->and(fn () => $fake->assertNothingStatusChanged())
        ->toThrow(AssertionFailedError::class);
});

it('fails assertStatusChanged when no status moved', function (): void {
    $fake = Reports::fake();

    $fake->assertNothingStatusChanged();

    expect(fn () => $fake->assertStatusChanged(Report::factory()->pending()->create()))
        ->toThrow(AssertionFailedError::class, 'Expected the report status to change.');
});

it('records moderation opened through the builder', function (): void {
    $fake = Reports::fake();
    $report = Report::factory()->pending()->create();

    $fake->assertNothingModerated();

    $request = Reports::moderate($report)->requiring([$this->user])->rule(ApprovalRule::Any)->open();

    $fake->assertModerated($report);

    expect($request->exists)->toBeFalse()
        ->and($request->rule)->toBe(ApprovalRule::Any)
        ->and($request->required_approvers)->toBe(1)
        ->and($report->approvalRequests()->count())->toBe(0)
        ->and(fn () => $fake->assertModerated(Report::factory()->pending()->create()))
        ->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertNothingModerated())
        ->toThrow(AssertionFailedError::class);
});

it('records prunes from the facade and the command', function (): void {
    $fake = Reports::fake();
    $old = Report::factory()->resolved()->create(['created_at' => now()->subDays(40)]);

    $fake->assertNothingPruned();

    expect(Reports::prune(30))->toBe(0);
    $this->artisan('reports:prune', ['--days' => 60, '--force' => true])->assertSuccessful();

    $fake->assertPruned();
    $fake->assertPruned(30);
    $fake->assertPruned(30, force: false);
    $fake->assertPruned(force: true);
    $fake->assertPruned(60, force: true);

    expect(Report::query()->find($old->getKey()))->not->toBeNull()
        ->and(fn () => $fake->assertPruned(7))
        ->toThrow(AssertionFailedError::class, 'with a 7-day window')
        ->and(fn () => $fake->assertPruned(30, force: true))
        ->toThrow(AssertionFailedError::class, 'permanently')
        ->and(fn () => $fake->assertNothingPruned())
        ->toThrow(AssertionFailedError::class);
});

it('fails assertPruned when nothing was pruned', function (): void {
    $fake = Reports::fake();

    expect(fn () => $fake->assertPruned())
        ->toThrow(AssertionFailedError::class, 'Expected reports to be pruned.')
        ->and(fn () => $fake->assertPruned(force: false))
        ->toThrow(AssertionFailedError::class, 'softly');
});

it('still answers the reason reads for real', function (): void {
    Reports::fake();

    expect(Reports::reasons())->toHaveKey('spam')
        ->and(Reports::reasonLabel('spam'))->toBe('Spam')
        ->and(Reports::defaultReason())->toBe('other')
        ->and(Reports::allowsReason('spam'))->toBeTrue();
});

it('never matches a different unsaved report or actor', function (): void {
    $fake = Reports::fake();

    // The fake files unsaved reports: every key is null, so Model::is() matched any two.
    [$a, $b, $c, $d, $e] = array_map(
        static fn (): Report => Reports::report(PostTestModel::create())->create(),
        range(1, 5),
    );
    $moderator = new UserTestModel;

    Reports::resolve($a, $moderator);
    Reports::reject($c);
    Reports::moderate($d)->requiring([$this->user])->open();
    Reports::review($e);

    $fake->assertResolved($a);
    $fake->assertResolved($a, $moderator);
    $fake->assertRejected($c);
    $fake->assertModerated($d);
    $fake->assertStatusChanged($e, Status::InReview);

    expect(fn () => $fake->assertResolved($b))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertResolved($a, new UserTestModel))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertRejected($b))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertModerated($b))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertStatusChanged($b))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertStatusChanged($b, Status::InReview))->toThrow(AssertionFailedError::class);
});
