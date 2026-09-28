<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Exceptions\MissingPruneWindowException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('documents its root, is fakeable and reaches every action', function (): void {
    expect(Reports::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('creates a report from a dto through the facade', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    $report = Reports::create(new CreateReportData(
        subject: $post,
        reason: Reason::Spam,
        reporter: $user,
        description: 'Link farm.',
    ));

    expect($report->exists)->toBeTrue()
        ->and($report->reason)->toBe('spam')
        ->and($report->status)->toBe(Status::Pending)
        ->and($report->reporter_id)->toBe($user->getKey());
});

it('moves a report through review and close through the facade', function (): void {
    Event::fake(ReportStatusChanged::class);

    $report = Report::factory()->pending()->create();

    expect(Reports::review($report)->status)->toBe(Status::InReview)
        ->and(Reports::resolve($report)->status)->toBe(Status::Resolved)
        ->and(Reports::close($report)->status)->toBe(Status::Closed)
        ->and(Reports::changeStatus($report, Status::Pending)->status)->toBe(Status::Pending)
        ->and($report->fresh()?->status)->toBe(Status::Pending);

    Event::assertDispatchedTimes(ReportStatusChanged::class, 4);
});

it('refuses a transition the status graph does not allow', function (): void {
    $report = Report::factory()->pending()->create();

    Reports::close($report);
})->throws(InvalidStatusTransitionException::class);

it('opens moderation through the facade', function (): void {
    $report = Report::factory()->pending()->create();

    $request = Reports::moderate($report)
        ->requiring([UserTestModel::create(), UserTestModel::create()])
        ->rule(ApprovalRule::Quorum)
        ->quorum(1)
        ->open();

    expect($request->exists)->toBeTrue()
        ->and($request->rule)->toBe(ApprovalRule::Quorum)
        ->and($request->quorum)->toBe(1)
        ->and($request->required_approvers)->toBe(2)
        ->and($report->approvalRequests()->count())->toBe(1);
});

it('prunes old terminal reports through the facade', function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');

    $old = Report::factory()->resolved()->create(['created_at' => Carbon::now()->subDays(40)]);
    Report::factory()->rejected()->create(['created_at' => Carbon::now()->subDays(5)]);

    expect(Reports::prune(30))->toBe(1)
        ->and(Report::query()->find($old->getKey()))->toBeNull()
        ->and(Report::withTrashed()->find($old->getKey()))->not->toBeNull()
        // A forced prune also purges the rows an earlier soft prune trashed.
        ->and(Reports::prune(1, force: true))->toBe(2)
        ->and(Report::withTrashed()->count())->toBe(0);
});

it('prunes with the configured window and refuses without one', function (): void {
    Carbon::setTestNow('2026-06-18 20:00:00');
    Report::factory()->closed()->create(['created_at' => Carbon::now()->subDays(20)]);

    config()->set('reports.prune_after_days', 10);
    expect(Reports::prune())->toBe(1);

    config()->set('reports.prune_after_days', null);
    Reports::prune();
})->throws(MissingPruneWindowException::class);

it('serves the same api from an injected manager', function (): void {
    $manager = app(ReportsManager::class);

    $report = $manager->report(PostTestModel::create())->by(UserTestModel::create())->for('abuse')->create();

    expect($manager)->toBe(app(ReportsManager::class))
        ->and($manager->review($report)->status)->toBe(Status::InReview)
        ->and($manager->reasons())->toHaveKey('abuse')
        ->and($manager->prune(1))->toBe(0);
});
