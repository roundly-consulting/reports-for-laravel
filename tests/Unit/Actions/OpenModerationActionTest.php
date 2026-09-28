<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Reports\Actions\OpenModerationAction;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('opens a pending approval request for the report', function (): void {
    $report = Report::factory()->pending()->create();

    $request = app(OpenModerationAction::class)->execute(
        $report,
        [UserTestModel::create(), UserTestModel::create(), UserTestModel::create()],
        ApprovalRule::Quorum,
        2,
    );

    expect($request->status)->toBe(ApprovalStatus::Pending)
        ->and($request->rule)->toBe(ApprovalRule::Quorum)
        ->and($request->quorum)->toBe(2)
        ->and($request->required_approvers)->toBe(3)
        ->and($request->subject?->is($report))->toBeTrue();
});

it('refuses to open moderation without moderators', function (): void {
    app(OpenModerationAction::class)->execute(Report::factory()->pending()->create(), [], ApprovalRule::Any);
})->throws(MissingModeratorsException::class);
