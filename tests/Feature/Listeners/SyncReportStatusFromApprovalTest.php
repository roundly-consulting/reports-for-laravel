<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Events\ApprovalRequestResolved;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;

function approvalRequestFor(Report $report, ApprovalStatus $status): ApprovalRequest
{
    $request = new ApprovalRequest;
    $request->subject_id = $report->getKey();
    $request->subject_type = $report->getMorphClass();
    $request->rule = ApprovalRule::Unanimous;
    $request->required_approvers = 1;
    $request->status = $status;
    $request->save();

    return $request;
}

it('maps an approved request onto the report status', function (): void {
    $report = Report::factory()->pending()->create();
    $request = approvalRequestFor($report, ApprovalStatus::Approved);

    event(new ApprovalRequestResolved($request));

    expect($report->fresh()?->status)->toBe(Status::Resolved);
});

it('maps a rejected request onto the report status', function (): void {
    $report = Report::factory()->pending()->create();
    $request = approvalRequestFor($report, ApprovalStatus::Rejected);

    event(new ApprovalRequestResolved($request));

    expect($report->fresh()?->status)->toBe(Status::Rejected);
});

it('ignores cancelled and expired requests', function (): void {
    $report = Report::factory()->pending()->create();

    event(new ApprovalRequestResolved(approvalRequestFor($report, ApprovalStatus::Cancelled)));
    expect($report->fresh()?->status)->toBe(Status::Pending);

    event(new ApprovalRequestResolved(approvalRequestFor($report, ApprovalStatus::Expired)));
    expect($report->fresh()?->status)->toBe(Status::Pending);
});

it('ignores requests whose subject is not a report', function (): void {
    $post = PostTestModel::create();

    $request = new ApprovalRequest;
    $request->subject_id = $post->getKey();
    $request->subject_type = $post->getMorphClass();
    $request->rule = ApprovalRule::Unanimous;
    $request->status = ApprovalStatus::Approved;
    $request->save();

    event(new ApprovalRequestResolved($request));

    expect($post->fresh())->not->toBeNull();
});

it('is idempotent and fires ReportResolved once', function (): void {
    $report = Report::factory()->pending()->create();
    $request = approvalRequestFor($report, ApprovalStatus::Approved);

    Event::fake([ReportResolved::class]);

    event(new ApprovalRequestResolved($request));
    event(new ApprovalRequestResolved($request));

    expect($report->fresh()?->status)->toBe(Status::Resolved);
    Event::assertDispatched(ReportResolved::class, 1);
});

it('no-ops on an illegal transition under strict mode', function (): void {
    config()->set('reports.strict_transitions', true);

    $report = Report::factory()->closed()->create();
    $request = approvalRequestFor($report, ApprovalStatus::Approved);

    event(new ApprovalRequestResolved($request));

    expect($report->fresh()?->status)->toBe(Status::Closed);
});

it('applies an otherwise illegal transition when strict mode is off', function (): void {
    config()->set('reports.strict_transitions', false);

    $report = Report::factory()->rejected()->create();
    $request = approvalRequestFor($report, ApprovalStatus::Approved);

    event(new ApprovalRequestResolved($request));

    expect($report->fresh()?->status)->toBe(Status::Resolved);
});
