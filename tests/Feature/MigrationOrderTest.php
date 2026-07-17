<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\ReportsServiceProvider;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * This file replaces ~140 lines of hand-rolled reinvention: the suite carried its own
 * `migrationSources()` globber, its own `createdTables()` regex scraper, its own FK-edge
 * walker (three separate `preg_match_all` forms), and its own publish-and-migrate case
 * that copied files into a temp directory and ran `migrate` against SQLite.
 *
 * The ideas were right — it even pinned the edge count so the parse could not go vacuous.
 * But it checked the right ideas with the wrong engine: SQLite is the driver that cannot
 * fail an ordering check, since it happily creates a table pointing at a missing parent
 * and only complains at insert time. That is exactly how five packages shipped
 * uninstallable migration orders under green suites.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5,
 * on three packages). `count: 1` pins the file count so neither check can pass over an
 * empty or relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(ReportsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes every migration timestamp-injected into the host', function (): void {
    expect(ReportsServiceProvider::class)->toPublishMigrationsTimestamped('reports-migrations', 1);
});

/**
 * M — `toHaveRunnableMigrationOrder` — is deliberately NOT adopted, and this note is the
 * cause rather than an omission.
 *
 * `MigrationGraph::assertRunnable()` checks two independent things and only one is about
 * foreign keys: it also pins that a `Schema::table()` ALTER sorts at or after the CREATE
 * of the table it alters (approvals #2). Reports ships **one CREATE, zero FK edges and
 * zero ALTERs** — verified against the migration source, not the row spec — so *both*
 * halves are inert. There is no edge to order and no ALTER to place.
 *
 * The contrast with its own provider is the clean illustration: `approvals` also has 0
 * FKs but ships 2 ALTERs, so it adopts M with a `foreignKeys: 0` live pin; `connections`
 * has 0 FKs and 0 ALTERs and rejects it, as this row does. Same FK count, opposite
 * outcomes — the criterion is FK edges OR ALTERs.
 *
 * Every reporter/reported/resolved_by column is a `morphKey`, deliberately unconstrained
 * because a host's reporter and subject can live in any table — and, being key-type
 * configurable, may not even be a bigint. If a real FK or an ALTER is ever added, this
 * row must adopt M rather than inherit this note.
 */

/**
 * R — the real-engine proof. The deleted local version ran the published files against a
 * throwaway **SQLite** database, which is the engine that cannot fail this class of
 * check. `migrations: 1` pins the count, and the expectation additionally fails a set
 * that "applies cleanly" while creating no tables — an empty `up()` otherwise passes and
 * proves nothing.
 *
 * The negative control (`toRejectBrokenOrderOnConnection`) is deliberately NOT adopted:
 * it asserts the engine *refuses* a reordered set, and with a single migration the
 * reversed list is the same list — and with zero foreign keys Postgres has nothing to
 * refuse regardless, so it would fail loudly by design. That is the assertion working
 * correctly against a shape it does not fit, not a red to chase.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 1);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the driver the leg *declares* (TESTING_DB_DRIVER)
 * against what the connection itself *answers*, so a "pgsql" job that quietly ran on
 * SQLite — the exact failure the whole leg exists to prevent — is impossible rather than
 * merely detectable by reading a skip count. It caught the 3a decapitation.
 */
it('runs on the driver the leg declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The enum-backed `status`/`reason` columns and the key-type-aware morph columns are what
 * the drivers render differently. Pinning a round-trip on whatever engine the leg
 * configured proves the columns are usable rather than merely creatable.
 */
it('round-trips a report on the configured engine', function (): void {
    $user = UserTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $report = $user->giveReportTo($post, 'spamming every thread', Reason::Abuse);
    $fresh = $report->fresh();

    expect($fresh->status)->toBe(Status::Pending)
        ->and($fresh->reason)->toBe(Reason::Abuse->value)
        ->and($fresh->description)->toBe('spamming every thread')
        ->and($fresh->reported_type)->toBe($post->getMorphClass())
        ->and($fresh->reported_id)->toBe($post->getKey())
        ->and($fresh->reporter_id)->toBe($user->getKey())
        ->and($post->hasBeenReported())->toBeTrue();
});
