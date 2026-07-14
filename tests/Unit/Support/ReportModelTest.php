<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReportModel;
use RoundlyConsulting\Reports\Tests\Models\CustomReport;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('resolves the packaged model by default', function (): void {
    expect(ReportModel::class())->toBe(Report::class)
        ->and(ReportModel::new())->toBeInstanceOf(Report::class)
        ->and(ReportModel::query()->getModel())->toBeInstanceOf(Report::class);
});

it('resolves a host subclass', function (): void {
    config()->set('reports.model', CustomReport::class);

    expect(ReportModel::class())->toBe(CustomReport::class)
        ->and(ReportModel::new())->toBeInstanceOf(CustomReport::class)
        ->and(ReportModel::query()->getModel())->toBeInstanceOf(CustomReport::class);
});

it('falls back to the packaged model for a model that is not a report', function (): void {
    config()->set('reports.model', UserTestModel::class);

    expect(ReportModel::class())->toBe(Report::class);
});

it('throws when the configured model is not a model at all', function (): void {
    config()->set('reports.model', 'Not\\A\\Class');

    ReportModel::class();
})->throws(InvalidConfigurationException::class);
