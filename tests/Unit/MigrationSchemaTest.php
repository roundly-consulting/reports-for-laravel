<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reports\Models\Report;

function runReportsMigration(): void
{
    Schema::dropIfExists((string) config('reports.table'));

    $migration = require __DIR__.'/../../database/migrations/create_reports_table.php';
    $migration->up();
}

/**
 * The emitted `CREATE TABLE` statement, so a key-type regression cannot hide
 * behind a column-existence assertion.
 */
function emittedCreateTable(string $table): string
{
    /** @var list<object{sql: string|null}> $rows */
    $rows = DB::select('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

    return (string) ($rows[0]->sql ?? '');
}

/**
 * @return list<string>
 */
function emittedIndexes(string $table): array
{
    /** @var list<object{name: string}> $rows */
    $rows = DB::select('select name from sqlite_master where type = ? and tbl_name = ? order by name', ['index', $table]);

    return array_values(array_map(static fn (object $row): string => $row->name, $rows));
}

it('creates all expected columns with the bigint key type', function (): void {
    config()->set('reports.key_type', 'bigint');
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

it('emits the frozen bigint schema byte-for-byte', function (): void {
    config()->set('reports.key_type', 'bigint');
    config()->set('reports.table', 'reports');

    runReportsMigration();

    // The bigint default is the shipped schema — it must never drift.
    expect(emittedCreateTable('reports'))->toBe(
        'CREATE TABLE "reports" ("id" integer primary key autoincrement not null, '
        .'"reporter_type" varchar, "reporter_id" integer, '
        .'"reported_type" varchar, "reported_id" integer, '
        .'"resolved_by_type" varchar, "resolved_by_id" integer, '
        .'"status" varchar not null default \'pending\', "reason" varchar not null, '
        .'"description" text, "resolution_note" text, "guest_identifier" varchar, '
        .'"resolved_at" datetime, "created_at" datetime, "updated_at" datetime, "deleted_at" datetime)'
    );

    expect(emittedIndexes('reports'))->toBe([
        'reports_dedupe_index',
        'reports_guest_identifier_index',
        'reports_reason_index',
        'reports_reported_type_reported_id_index',
        'reports_reported_type_reported_id_status_index',
        'reports_reporter_type_reporter_id_index',
        'reports_resolved_by_type_resolved_by_id_index',
        'reports_status_index',
    ]);
});

it('persists a guest report with a null reporter', function (): void {
    runReportsMigration();

    $report = Report::factory()->guest('hash')->create();

    expect($report->reporter_id)->toBeNull()
        ->and($report->guest_identifier)->toBe('hash');
});

it('creates uuid morph columns when configured', function (): void {
    config()->set('reports.key_type', 'uuid');
    config()->set('reports.table', 'uuid_reports');

    runReportsMigration();

    expect(Schema::hasColumns('uuid_reports', [
        'reporter_id', 'reporter_type', 'reported_id', 'resolved_by_id',
    ]))->toBeTrue();

    // SQLite reports uuid columns as varchar/text, not integer.
    expect(Schema::getColumnType('uuid_reports', 'reporter_id'))->not->toBe('integer');
    expect(emittedCreateTable('uuid_reports'))
        ->toContain('"reporter_id" varchar')
        ->toContain('"reported_id" varchar')
        ->toContain('"resolved_by_id" varchar');

    Schema::dropIfExists('uuid_reports');
});

it('creates ulid morph columns when configured', function (): void {
    config()->set('reports.key_type', 'ulid');
    config()->set('reports.table', 'ulid_reports');

    runReportsMigration();

    expect(Schema::hasColumns('ulid_reports', [
        'reporter_id', 'reporter_type', 'reported_id', 'resolved_by_id',
    ]))->toBeTrue();

    expect(Schema::getColumnType('ulid_reports', 'reporter_id'))->not->toBe('integer');
    expect(emittedCreateTable('ulid_reports'))
        ->toContain('"reporter_id" varchar')
        ->toContain('"reported_id" varchar')
        ->toContain('"resolved_by_id" varchar');

    Schema::dropIfExists('ulid_reports');
});

it('falls back to the bigint schema for an unrecognized key type', function (): void {
    config()->set('reports.key_type', 'nonsense');
    config()->set('reports.table', 'fallback_reports');

    runReportsMigration();

    expect(Schema::getColumnType('fallback_reports', 'reporter_id'))->toBe('integer');

    Schema::dropIfExists('fallback_reports');
});
