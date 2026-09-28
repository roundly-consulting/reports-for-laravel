<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Filing a report was check-then-act: the duplicate lookup, the insert and the threshold
 * count ran as separate statements, so two concurrent filings (a double-submit, or two
 * reporters crossing the threshold together) could both pass the duplicate check, or
 * both miss — or both hit — the threshold. Filing now locks the reported subject's row
 * and runs all three inside one transaction, so filings against one subject serialize.
 */
it('locks the subject, then dedupes, inserts and counts inside one transaction', function (): void {
    config()->set('reports.threshold', 1);
    Event::fake([ReportThresholdReached::class]);

    $post = PostTestModel::create();
    $user = UserTestModel::create();

    /** @var list<array{sql: string, level: int}> $log */
    $log = [];
    DB::listen(function (QueryExecuted $query) use (&$log): void {
        $log[] = ['sql' => strtolower($query->sql), 'level' => DB::transactionLevel()];
    });

    Reports::report($post)->by($user)->create();

    $table = (new Report)->getTable();
    $position = static function (callable $match) use ($log): int {
        foreach ($log as $index => $entry) {
            if ($match($entry['sql'])) {
                return $index;
            }
        }

        return -1;
    };

    $lock = $position(static fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, '"posts"'));
    $dedupe = $position(static fn (string $sql): bool => str_starts_with($sql, 'select') && str_contains($sql, "\"{$table}\"") && str_contains($sql, 'reporter_id'));
    $insert = $position(static fn (string $sql): bool => str_starts_with($sql, 'insert') && str_contains($sql, "\"{$table}\""));
    $count = $position(static fn (string $sql): bool => str_starts_with($sql, 'select count') && str_contains($sql, "\"{$table}\""));

    expect($lock)->toBeGreaterThanOrEqual(0)
        ->and($dedupe)->toBeGreaterThan($lock)
        ->and($insert)->toBeGreaterThan($dedupe)
        ->and($count)->toBeGreaterThan($insert);

    foreach ([$lock, $dedupe, $insert, $count] as $index) {
        expect($log[$index]['level'])->toBeGreaterThan(0);
    }

    // The event announces a committed fact: it fires after the transaction, once.
    Event::assertDispatchedTimes(ReportThresholdReached::class, 1);
});

/**
 * The real-engine proof: while a second connection holds the subject's row lock (another
 * filing in flight), a filing waits on that lock — here it gives up after the lock
 * timeout — before it looks for duplicates.
 */
it('waits for the subject row lock before looking for duplicates on postgres', function (): void {
    $post = PostTestModel::create();
    $user = UserTestModel::create();

    config()->set('database.connections.rival', config('database.connections.'.config('database.default')));
    $rival = DB::connection('rival');
    $rival->beginTransaction();
    $rival->table('posts')->where('id', $post->getKey())->lockForUpdate()->first();

    DB::statement("set lock_timeout = '300ms'");

    try {
        Reports::report($post)->by($user)->create();
        $this->fail('Filing did not wait for the subject row lock.');
    } catch (QueryException $e) {
        expect(strtolower($e->getSql()))->toContain('"posts"')->toContain('for update');
    } finally {
        $rival->rollBack();
        DB::purge('rival');
    }

    expect(Report::query()->count())->toBe(0);
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql', 'row locks need a real engine');
