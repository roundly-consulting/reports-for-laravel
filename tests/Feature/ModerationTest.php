<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('opens one approval request with the declared rule and quorum', function (): void {
    $report = Report::factory()->pending()->create();
    $m1 = UserTestModel::create();
    $m2 = UserTestModel::create();

    $request = Reports::moderate($report)
        ->requiring([$m1, $m2])
        ->rule(ApprovalRule::Quorum)
        ->quorum(2)
        ->open();

    expect($request->rule)->toBe(ApprovalRule::Quorum)
        ->and($request->quorum)->toBe(2)
        ->and($request->required_approvers)->toBe(2)
        ->and($report->approvalRequests()->count())->toBe(1)
        ->and($report->currentApprovalStatus())->toBe(ApprovalStatus::Pending);
});

it('throws when opening moderation without moderators', function (): void {
    $report = Report::factory()->pending()->create();

    Reports::moderate($report)->open();
})->throws(MissingModeratorsException::class);

it('holds the report open until the quorum is reached then resolves it', function (): void {
    $report = Report::factory()->pending()->create();
    $m1 = UserTestModel::create();
    $m2 = UserTestModel::create();

    Reports::moderate($report)->requiring([$m1, $m2])->rule(ApprovalRule::Quorum)->quorum(2)->open();

    Event::fake([ReportResolved::class, ReportStatusChanged::class]);

    Reports::resolve($report, $m1);

    expect($report->fresh()?->status)->toBe(Status::Pending);
    Event::assertNotDispatched(ReportResolved::class);

    Reports::resolve($report, $m2, 'Removed.');

    $fresh = $report->fresh();

    expect($fresh?->status)->toBe(Status::Resolved)
        ->and($fresh?->resolution_note)->toBe('Removed.')
        ->and($fresh?->resolved_by_id)->toBe($m2->getKey());

    Event::assertDispatched(ReportResolved::class, 1);
    Event::assertDispatched(ReportStatusChanged::class, 1);
});

it('rejects the report through the engine when a moderator rejects', function (): void {
    $report = Report::factory()->pending()->create();
    $m1 = UserTestModel::create();
    $m2 = UserTestModel::create();

    Reports::moderate($report)->requiring([$m1, $m2])->rule(ApprovalRule::Unanimous)->open();

    Event::fake([ReportRejected::class, ReportStatusChanged::class]);

    Reports::reject($report, $m1, 'Spam.');

    $fresh = $report->fresh();

    expect($fresh?->status)->toBe(Status::Rejected)
        ->and($fresh?->resolution_note)->toBe('Spam.')
        ->and($fresh?->resolved_by_id)->toBe($m1->getKey());

    Event::assertDispatched(ReportRejected::class, 1);
});

it('resolves immediately when there is no open moderation request', function (): void {
    $report = Report::factory()->pending()->create();
    $admin = UserTestModel::create();

    $resolved = Reports::resolve($report, $admin, 'Done.');

    expect($resolved->status)->toBe(Status::Resolved)
        ->and($resolved->resolved_by_id)->toBe($admin->getKey())
        ->and($resolved->resolution_note)->toBe('Done.');
});

it('resolves immediately for a null actor even with an open moderation request', function (): void {
    $report = Report::factory()->pending()->create();

    Reports::moderate($report)->requiring([UserTestModel::create()])->open();

    $resolved = Reports::resolve($report);

    expect($resolved->status)->toBe(Status::Resolved)
        ->and($resolved->resolved_by_id)->toBeNull();
});
