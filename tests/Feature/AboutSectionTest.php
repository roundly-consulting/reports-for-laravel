<?php

declare(strict_types=1);

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`,
 * which returns `''`. Every "does not leak" check was vacuous — passing against empty
 * output.
 *
 * Reports' existing about tests in ServiceProviderTest are NOT that bug — they capture
 * through `Artisan::call` + `Artisan::output()` and do assert positives first, which is
 * the right shape. They are consolidated here so the capture goes through the shared
 * assertion, which enforces that ordering by construction rather than by the author
 * having remembered it: output non-empty, then every mustRender string, and only then
 * the secret scan.
 *
 * Reports carries no credentials, which is exactly why it is worth pinning: the risk here
 * is the host's **moderation vocabulary and its live caseload**. A reason slug
 * ("staff-misconduct") is the host's private taxonomy, and a report names a reporter and
 * a subject. The provider is deliberately written to report reasons by count, the default
 * reason by DEFAULT/CUSTOM, and the threshold as a bound — never a slug, never a subject,
 * never a stored report. This test is what stops a future "helpful" change rendering them.
 */
it('renders the reports section without leaking the host moderation vocabulary', function (): void {
    config()->set('reports.reasons', ['insider-trading-tip', 'staff-misconduct']);
    config()->set('reports.default_reason', 'staff-misconduct');
    config()->set('reports.threshold', 5);
    config()->set('reports.prune_after_days', 30);
    config()->set('reports.moderation.default_quorum', 2);
    config()->set('reports.moderation.default_rule', 'quorum');

    expect('reports')->toLeakNoSecrets(
        secrets: [
            // A reason slug is the host's moderation taxonomy — reported by count only.
            'insider-trading-tip',
            'staff-misconduct',
        ],
        mustRender: [
            'Model',
            'Table',
            'Key type',
            'Reasons',
            'Default reason',
            'Duplicate prevention',
            'Strict transitions',
            'Threshold',
            'Pruning',
            'Moderation',
            // The positive halves that prove the lines report rather than sit silently
            // empty: the count, the marker that a custom default is set, and the bounds.
            '2 allowed',
            'unknown REJECTED',
            'CUSTOM',
            '5 open report(s)',
            '30 day(s)',
            'quorum 2',
            'bigint',
        ],
    );
});

/**
 * The other branch of every switch. Pinned because these are the shipped defaults — the
 * branch every host sees first, and the one a single-scenario test never renders. The
 * ALL MODERATORS / DISABLED / MANUAL markers are the ones that must not read as an empty
 * line, which would look like a rendering bug rather than a deliberate default.
 */
it('reports the shipped defaults and every switched-off setting', function (): void {
    config()->set('reports.prevent_duplicates', false);
    config()->set('reports.strict_transitions', false);

    expect('reports')->toLeakNoSecrets(
        secrets: ['insider-trading-tip'],
        mustRender: [
            'Reasons',
            '6 allowed',
            'DEFAULT',
            // prevent_duplicates false and strict_transitions false both render OFF.
            'OFF',
            // threshold and prune_after_days both default to null.
            'DISABLED',
            'MANUAL',
            'ALL MODERATORS',
        ],
    );
});

/**
 * The duplicate-scope line has three states (OFF / ON scope open / ON scope any) and the
 * third is reachable only through this key. A scope reported wrong is a real host-visible
 * bug — "any" means a resolved report still blocks a new one — so it is pinned rather
 * than left to the two cases above.
 */
it('reports the any duplicate scope', function (): void {
    config()->set('reports.duplicate_scope', 'any');

    expect('reports')->toLeakNoSecrets(
        secrets: ['staff-misconduct'],
        mustRender: ['Duplicate prevention', 'ON (scope any)'],
    );
});
