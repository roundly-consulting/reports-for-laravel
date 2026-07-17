<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Testing\Database\DriverMatrix;

function runReportsMigration(): void
{
    Schema::dropIfExists((string) config('reports.table'));

    $migration = require __DIR__.'/../../database/migrations/create_reports_table.php';
    $migration->up();
}

/**
 * The emitted `CREATE TABLE` statement, so a key-type regression cannot hide behind a
 * column-existence assertion. SQLite-only by construction — `sqlite_master` is the
 * catalog, and this whole helper is why the cases using it are driver-gated below.
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

/**
 * The Postgres catalog's answer for a column: the real type, and its length where it has
 * one. This is what makes the key-type cases below able to fail at all.
 */
function pgsqlColumnType(string $table, string $column): string
{
    /** @var list<object{data_type: string, character_maximum_length: int|null}> $rows */
    $rows = DB::select(
        'select data_type, character_maximum_length from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    $row = $rows[0] ?? null;

    if ($row === null) {
        return 'MISSING';
    }

    return $row->character_maximum_length === null
        ? $row->data_type
        : $row->data_type.'('.$row->character_maximum_length.')';
}

$sqliteOnly = fn (): bool => DriverMatrix::driver() !== 'sqlite';
$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('creates all expected columns', function (): void {
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
})->skip($sqliteOnly, 'sqlite_master is the sqlite catalog');

it('persists a guest report with a null reporter', function (): void {
    runReportsMigration();

    $report = Report::factory()->guest('hash')->create();

    expect($report->reporter_id)->toBeNull()
        ->and($report->guest_identifier)->toBe('hash');
});

/**
 * The key-type cases, on the only engine that can answer them.
 *
 * These used to run on SQLite and assert `getColumnType(...)` is `integer` for bigint,
 * and merely `not->toBe('integer')` / `toContain('"reporter_id" varchar')` for uuid and
 * ulid. Every one of those was **structurally incapable of biting**, which is the exact
 * finding the package-toolkit row made against this same class of assertion:
 *
 *  - SQLite reports `unsignedBigInteger()` and `integer()` **alike** as `integer`, so the
 *    bigint pin could not catch the 32-bit downgrade the toolkit found in `ownerKey`;
 *  - SQLite stores uuid and ulid **both** as `varchar`, so the uuid case passed verbatim
 *    against a ulid column and vice versa. Two tests, one indistinguishable assertion.
 *
 * `reports.key_type` is this package's headline config — the whole reason a uuid/ulid host
 * can use it — and it had only ever been checked on the one engine that cannot tell the
 * three apart. Postgres reports `bigint`, `uuid` and `character(26)` distinctly, so here
 * the assertion has something to say.
 */
it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected): void {
    config()->set('reports.key_type', $keyType);
    config()->set('reports.table', 'kt_reports');

    Schema::dropIfExists('kt_reports');
    $migration = require __DIR__.'/../../database/migrations/create_reports_table.php';
    $migration->up();

    // Every polymorphic id column follows the configured type, not just the first.
    expect(pgsqlColumnType('kt_reports', 'reporter_id'))->toBe($expected)
        ->and(pgsqlColumnType('kt_reports', 'reported_id'))->toBe($expected)
        ->and(pgsqlColumnType('kt_reports', 'resolved_by_id'))->toBe($expected)
        // The morph *type* column is a string on every key type — it names a class.
        ->and(pgsqlColumnType('kt_reports', 'reported_type'))->toBe('character varying(255)');

    Schema::dropIfExists('kt_reports');
})->with([
    // The shipped default. `bigint`, never `integer` — a 32-bit id column silently caps a
    // host's table at 2.1bn rows, and SQLite calls both of them `integer`.
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart');

it('falls back to the bigint schema for an unrecognized key type', function (): void {
    config()->set('reports.key_type', 'nonsense');
    config()->set('reports.table', 'fallback_reports');

    runReportsMigration();

    // Silently falling back is the documented behaviour: a typo in a host's config must
    // never leave the package unable to migrate.
    expect(Schema::hasColumn('fallback_reports', 'reporter_id'))->toBeTrue()
        ->and(DatabaseDriver::current()->isPgsql() ? pgsqlColumnType('fallback_reports', 'reporter_id') : 'bigint')
        ->toBe('bigint');

    Schema::dropIfExists('fallback_reports');
});
