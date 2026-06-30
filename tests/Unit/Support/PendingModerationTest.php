<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\PendingModeration;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('defaults to the unanimous rule with no quorum', function (): void {
    $report = Report::factory()->pending()->create();

    $request = (new PendingModeration($report))->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Unanimous)
        ->and($request->quorum)->toBeNull();
});

it('seeds defaults from config', function (): void {
    config()->set('reports.moderation.default_rule', 'any');
    config()->set('reports.moderation.default_quorum', 3);

    $report = Report::factory()->pending()->create();

    $request = (new PendingModeration($report))->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Any)
        ->and($request->quorum)->toBe(3);
});

it('falls back to unanimous and null quorum on invalid config', function (): void {
    config()->set('reports.moderation.default_rule', null);
    config()->set('reports.moderation.default_quorum', 'not-an-int');

    $report = Report::factory()->pending()->create();

    $request = (new PendingModeration($report))->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Unanimous)
        ->and($request->quorum)->toBeNull();
});

it('ignores an unknown default rule string', function (): void {
    config()->set('reports.moderation.default_rule', 'garbage');

    $report = Report::factory()->pending()->create();

    $request = (new PendingModeration($report))->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Unanimous);
});

it('throws when no moderators are declared', function (): void {
    $report = Report::factory()->pending()->create();

    (new PendingModeration($report))->open();
})->throws(MissingModeratorsException::class);
