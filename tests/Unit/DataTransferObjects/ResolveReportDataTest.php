<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Tests\UserTestModel;

it('defaults to a system resolution with no resolver or note', function (): void {
    $data = new ResolveReportData;

    expect($data->resolver)->toBeNull()
        ->and($data->note)->toBeNull();
});

it('carries the resolver and note', function (): void {
    $admin = new UserTestModel(['id' => 9]);

    $data = new ResolveReportData(resolver: $admin, note: 'Handled.');

    expect($data->resolver)->toBe($admin)
        ->and($data->note)->toBe('Handled.');
});
