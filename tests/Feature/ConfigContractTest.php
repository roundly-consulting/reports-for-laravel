<?php

declare(strict_types=1);

/**
 * The config contract, pinned in both directions.
 *
 * This file replaces ~150 lines of hand-rolled reinvention: the suite carried its own
 * `configKeysReadIn()` tokenizer, its own `packageSourceFiles()` walker, its own
 * `flattenConfigKeys()` flattener, and its own forward/reverse cases. The ideas were
 * right — it even tokenized rather than regexed, which is the trap media #27 fell into —
 * and that is exactly why it should be the shared implementation rather than this
 * package's copy of it.
 *
 * What the local version could not do, and the preset does:
 *  - it counted only `'reports.…'` string literals, so an injected
 *    `Repository::get('reports.x')` or a `Config::get()` read was invisible to it;
 *  - it silently ignored interpolated keys instead of flagging them as uncheckable;
 *  - `allowUnread`/`allowUnshipped` are rot-proof here — a stale entry that silences
 *    nothing is itself a failure. A hand-rolled skip list rots quietly.
 *
 * The bugs both directions exist for:
 *  - forward — shops #18: the whole store-credit feature read `shops.payments.*` while
 *    the file shipped `payment.*`; 330 tests stayed green because the suite set the same
 *    wrong key.
 *  - reverse — media #27's `max_file_size` cap that never applied, alerts #24's
 *    thrice-documented `escalation` key. Reports is a package where that lie is
 *    expensive: a host reading `reports.threshold` in the file believes moderators get
 *    paged, and `reports.prevent_duplicates` that a brigade cannot pile on one subject.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/reports.php')->toSatisfyConfigContract(
        [__DIR__.'/../../src', __DIR__.'/../../database'],
        [
            // `reports.model` is read through the toolkit's `ModelResolver::for(…)` seam
            // (via Support\ReportModel) and `reports.key_type` through
            // `KeyType::fromConfig(…)` in the migration. Both are real reads — one drives
            // the model swap, the other decides the shipped column types — but neither is
            // a `config(` token, so the prefix is what makes them visible to the scraper.
            'extraReadPrefixes' => ['reports.'],

            // Deliberately NO `excludeFromReverse` for the provider. The testing README's
            // own example excludes the service provider on the grounds that "a render is
            // not a read" — but this provider's `aboutPayload()` calls `config('reports.…')`
            // for real (table, threshold, prune_after_days, moderation.*,
            // strict_transitions, prevent_duplicates, duplicate_scope), and for several of
            // those it is the only reader in the package. Excluding it would discard
            // readers and weaken the reverse direction for nothing.
        ],
    );
});
