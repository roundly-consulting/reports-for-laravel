<?php

declare(strict_types=1);

use RoundlyConsulting\Approvals\Enums\ApprovalRule;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Exceptions\MissingModeratorsException;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('defaults to the unanimous rule with no quorum', function (): void {
    $report = Report::factory()->pending()->create();

    $request = Reports::moderate($report)->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Unanimous)
        ->and($request->quorum)->toBeNull();
});

it('seeds defaults from config', function (): void {
    config()->set('reports.moderation.default_rule', 'any');
    config()->set('reports.moderation.default_quorum', 3);

    $report = Report::factory()->pending()->create();

    $request = Reports::moderate($report)->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Any)
        ->and($request->quorum)->toBe(3);
});

it('uses unanimous and no quorum when the defaults are absent or blank (strict config)', function (?string $value): void {
    config()->set('reports.moderation.default_rule', $value);
    config()->set('reports.moderation.default_quorum', $value);

    $report = Report::factory()->pending()->create();

    $request = Reports::moderate($report)->requiring([UserTestModel::create()])->open();

    expect($request->rule)->toBe(ApprovalRule::Unanimous)
        ->and($request->quorum)->toBeNull();
})->with(['absent' => [null], 'blank' => [''], 'whitespace' => [' ']]);

it('refuses a junk default quorum instead of requiring every moderator (strict config)', function (mixed $value, string $message): void {
    config()->set('reports.moderation.default_quorum', $value);

    $report = Report::factory()->pending()->create();

    expect(fn () => Reports::moderate($report))->toThrow(InvalidConfigurationException::class, $message);
})->with([
    'junk' => ['not-an-int', 'Configuration value [reports.moderation.default_quorum] must be an integer, [not-an-int] given.'],
    'decimal' => ['2.5', 'Configuration value [reports.moderation.default_quorum] must be an integer, [2.5] given.'],
    'zero' => [0, 'Configuration value [reports.moderation.default_quorum] must be at least 1, [0] given.'],
]);

it('refuses an unknown default rule instead of using unanimous (strict config)', function (): void {
    config()->set('reports.moderation.default_rule', 'garbage');

    $report = Report::factory()->pending()->create();

    expect(fn () => Reports::moderate($report))->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [reports.moderation.default_rule] must be one of [unanimous, quorum, any, weighted], [garbage] given.',
    );
});

it('throws when no moderators are declared', function (): void {
    $report = Report::factory()->pending()->create();

    Reports::moderate($report)->open();
})->throws(MissingModeratorsException::class);
