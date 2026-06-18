<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reports\Models\Report;

function runReportsMigration(): void
{
    Schema::dropIfExists((string) config('reports.table'));

    $migration = require __DIR__.'/../../database/migrations/create_reports_table.php';
    $migration->up();
}

it('creates all expected columns with the bigint key type', function (): void {
    config()->set('reports.morph_key_type', 'bigint');
    config()->set('reports.table', 'reports');

    runReportsMigration();

    expect(Schema::hasColumns('reports', [
        'id',
        'reporter_id', 'reporter_type',
        'reported_id', 'reported_type',
        'resolved_by_id', 'resolved_by_type',
        'status', 'reason', 'description', 'resolution_note',
        'guest_identifier', 'resolved_at',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    expect(Schema::getColumnType('reports', 'reporter_id'))->toBe('integer');
});

it('persists a guest report with a null reporter', function (): void {
    runReportsMigration();

    $report = Report::factory()->guest('hash')->create();

    expect($report->reporter_id)->toBeNull()
        ->and($report->guest_identifier)->toBe('hash');
});

it('creates uuid morph columns when configured', function (): void {
    config()->set('reports.morph_key_type', 'uuid');
    config()->set('reports.table', 'uuid_reports');

    runReportsMigration();

    expect(Schema::hasColumns('uuid_reports', [
        'reporter_id', 'reporter_type', 'reported_id', 'resolved_by_id',
    ]))->toBeTrue();

    // SQLite reports uuid columns as varchar/text, not integer.
    expect(Schema::getColumnType('uuid_reports', 'reporter_id'))->not->toBe('integer');

    Schema::dropIfExists('uuid_reports');
    config()->set('reports.morph_key_type', 'bigint');
    config()->set('reports.table', 'reports');
});
