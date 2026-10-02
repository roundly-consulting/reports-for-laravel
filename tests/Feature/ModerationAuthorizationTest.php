<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Exceptions\InvalidApprovalRequestException;
use RoundlyConsulting\Approvals\Exceptions\UnauthorizedApprovalException;
use RoundlyConsulting\Approvals\Facades\Approvals;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Events\ReportResolved;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\ModeratorRequiredException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PlainUserTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

/**
 * Only the moderators a moderation request names may settle the report. Before, any
 * approver could sign off: a unanimous [alice, bob] request was resolved by two
 * outsiders, and a null or non-approver actor settled the report directly while the
 * approval request stayed pending forever.
 */
beforeEach(function (): void {
    $this->alice = UserTestModel::create();
    $this->bob = UserTestModel::create();
    $this->mallory = UserTestModel::create();
    $this->trudy = UserTestModel::create();
    $this->report = Report::factory()->pending()->create();
});

/**
 * Asserts nothing about the report or its approval request moved.
 */
function expectModerationUntouched(Report $report, ApprovalRequest $request): void
{
    $fresh = $report->fresh();

    expect($fresh?->status)->toBe(Status::Pending)
        ->and($fresh?->resolved_by_id)->toBeNull()
        ->and($fresh?->resolution_note)->toBeNull()
        ->and($fresh?->resolved_at)->toBeNull()
        ->and($request->fresh()?->status)->toBe(ApprovalStatus::Pending)
        ->and($request->decisions()->count())->toBe(0);
}

it('refuses outsiders mallory and trudy on a unanimous [alice, bob] moderation', function (): void {
    $request = Reports::moderate($this->report)
        ->requiring([$this->alice, $this->bob])
        ->rule(ApprovalRule::Unanimous)
        ->open();

    Event::fake([ReportResolved::class, ReportRejected::class, ReportStatusChanged::class]);

    foreach ([$this->mallory, $this->trudy] as $outsider) {
        foreach (['resolve', 'reject'] as $verb) {
            try {
                Reports::{$verb}($this->report, $outsider, 'outsiders');
                $this->fail("An outsider could {$verb} a moderated report.");
            } catch (ModeratorRequiredException $e) {
                expect($e->report->is($this->report))->toBeTrue()
                    ->and($e->actor?->is($outsider))->toBeTrue()
                    ->and($e->getPrevious())->toBeInstanceOf(UnauthorizedApprovalException::class);
            }
        }
    }

    expectModerationUntouched($this->report, $request);
    Event::assertNothingDispatched();

    // The named moderators still decide it.
    Reports::resolve($this->report, $this->alice);
    Reports::resolve($this->report, $this->bob, 'Spam');

    expect($this->report->fresh()?->status)->toBe(Status::Resolved)
        ->and($this->report->fresh()?->resolved_by_id)->toBe($this->bob->getKey());
});

it('refuses an outsider under the quorum and any rules too', function (ApprovalRule $rule, ?int $quorum): void {
    $moderation = Reports::moderate($this->report)->requiring([$this->alice, $this->bob])->rule($rule);

    if ($quorum !== null) {
        $moderation->quorum($quorum);
    }

    $request = $moderation->open();

    expect(fn () => Reports::resolve($this->report, $this->mallory))
        ->toThrow(ModeratorRequiredException::class);

    expectModerationUntouched($this->report, $request);
})->with([
    'quorum of 1' => [ApprovalRule::Quorum, 1],
    'any' => [ApprovalRule::Any, null],
]);

it('refuses a null actor while moderation is open, leaving both sides pending', function (string $verb): void {
    $request = Reports::moderate($this->report)->requiring([$this->alice, $this->bob])->open();

    try {
        Reports::{$verb}($this->report);
        $this->fail("A null actor could {$verb} a moderated report.");
    } catch (ModeratorRequiredException $e) {
        expect($e->actor)->toBeNull()
            ->and($e->getMessage())->toContain('under moderation');
    }

    expectModerationUntouched($this->report, $request);
})->with(['resolve', 'reject']);

it('refuses an actor that cannot give approvals while moderation is open', function (string $verb): void {
    $request = Reports::moderate($this->report)->requiring([$this->alice])->open();
    $admin = PlainUserTestModel::create();

    expect(fn () => Reports::{$verb}($this->report, $admin))
        ->toThrow(ModeratorRequiredException::class);

    expectModerationUntouched($this->report, $request);
})->with(['resolve', 'reject']);

it('lets a delegate of a named moderator decide for them', function (): void {
    $deputy = UserTestModel::create();
    Approvals::delegations($this->alice)->to($deputy)->grant();

    Reports::moderate($this->report)->requiring([$this->alice])->open();
    Reports::resolve($this->report, $deputy, 'On behalf of alice.');

    // Recorded for the delegator, as the approvals engine does for every delegate.
    expect($this->report->fresh()?->status)->toBe(Status::Resolved)
        ->and($this->report->fresh()?->resolved_by_id)->toBe($this->alice->getKey());
});

it('lets a named moderator decide even when the model does not use GivesApprovals', function (): void {
    $moderator = PlainUserTestModel::create();

    Reports::moderate($this->report)->requiring([$moderator])->open();

    Reports::reject($this->report, $moderator, 'Fine as is.');

    expect($this->report->fresh()?->status)->toBe(Status::Rejected)
        ->and($this->report->fresh()?->resolution_note)->toBe('Fine as is.');
});

it('refuses a raw move out of the open statuses while moderation is open', function (Status $to): void {
    config()->set('reports.strict_transitions', false);
    $request = Reports::moderate($this->report)->requiring([$this->alice])->open();

    expect(fn () => Reports::changeStatus($this->report, $to))
        ->toThrow(ModeratorRequiredException::class);

    expectModerationUntouched($this->report, $request);
})->with([Status::Resolved, Status::Rejected, Status::Closed]);

it('still lets a moderated report move between open statuses', function (): void {
    Reports::moderate($this->report)->requiring([$this->alice])->open();

    expect(Reports::review($this->report)->status)->toBe(Status::InReview)
        ->and($this->report->isUnderModeration())->toBeTrue();

    Reports::resolve($this->report, $this->alice);

    expect($this->report->fresh()?->status)->toBe(Status::Resolved)
        ->and($this->report->isUnderModeration())->toBeFalse();
});

it('settles directly again once the moderation request is decided', function (): void {
    Reports::moderate($this->report)->requiring([$this->alice])->open();
    Reports::reject($this->report, $this->alice);

    // Reopened after the request resolved: no open moderation, so anyone may settle it.
    Reports::changeStatus($this->report, Status::Pending);

    expect(Reports::resolve($this->report)->status)->toBe(Status::Resolved);
});

it('refuses to open a moderation the named moderators could never settle', function (Closure $open, string $message): void {
    expect(fn () => $open($this))->toThrow(InvalidApprovalRequestException::class, $message)
        ->and($this->report->isUnderModeration())->toBeFalse()
        ->and(ApprovalRequest::query()->count())->toBe(0);
})->with([
    'a quorum above the moderator count' => [
        fn (object $test): ApprovalRequest => Reports::moderate($test->report)
            ->requiring([$test->alice, $test->bob])
            ->rule(ApprovalRule::Quorum)
            ->quorum(3)
            ->open(),
        'can never be met',
    ],
    'an unsaved moderator' => [
        fn (object $test): ApprovalRequest => Reports::moderate($test->report)
            ->requiring([$test->alice, new UserTestModel])
            ->open(),
        'must be saved',
    ],
]);
