<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Exceptions\ReportsException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Reports shipped with no architecture test at all, so every preset here is a new guard
 * rather than a replacement — including the one that names this package directly.
 */
ArchPresets::strictTypes('RoundlyConsulting\Reports');

/**
 * The deliberate extension points are exempt: `Report` is what `reports.model` invites a
 * host to subclass (pinned by the preset below instead), ReportsException is the base
 * every reports error extends so a host can catch them uniformly, and ReportsManager is
 * the facade root `ReportsFake` extends, so a constructor-injected manager receives the
 * fake under `Reports::fake()`.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Reports', [
    Report::class,
    ReportsException::class,
    ReportsManager::class,
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal — **reports #33 is one of the
 * seven**. `final` on a config-swappable model is a PHP fatal the moment a host uses the
 * seam the config documents, and this package shipped exactly that. It was fixed in the
 * toolkit retrofit, but nothing has stopped it coming back since: reports had no arch
 * test to notice. This preset is that guard, and it also pins that `reports.model` really
 * defaults to the packaged Report, so the seam cannot rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Report::class => 'reports.model',
]);

/**
 * Reports does no cryptography — the one thing that looks like it (`guest_identifier`, a
 * hashed IP/email used to dedupe anonymous reports) is hashed by the host before it ever
 * reaches this package. The ban is a standing guard against that hashing being
 * "helpfully" reimplemented here rather than taken from crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Reports');

/**
 * Every `reports.model` read goes through Support\ReportModel (which delegates to the
 * toolkit's ModelResolver). Adopted rather than rejected as jwt rejected it: reports has
 * exactly the shape the preset targets — a real Eloquent model behind a `*model` key,
 * resolved through a Support seam — so the stray-literal half has something to say.
 *
 * This replaces a hand-rolled equivalent that stood in ConfigContractTest.php ("resolves
 * the report model only through the Support seam"), which checked the stray-literal half
 * by tokenizing for `reports.model` outside `/Support/`. The preset does that AND bans
 * the late-static-binding half (`static::query()`/`new static` resolving the *called*
 * class rather than the configured one — permissions #34), which the local version never
 * checked.
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support', ['reports.model']);

/**
 * The morph-key seam, guarded. Reports' `reporter` and `resolved_by` columns migrated off
 * raw `$table->morphs()` onto `morphKey($name, KeyType::fromConfig(...))` so a uuid/ulid
 * host can flip its whole graph coherently — a hardcoded bigint id breaks those hosts on
 * Postgres, and SQLite type affinity hides it. This pin reds if a future migration
 * reintroduces a raw morph and bypasses the seam.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

/**
 * The Dependency Policy as a test. No `alsoAllow`: reports' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/**
 * `Report::changeStatusTo()` and the GivesReports trait delegate to the manager, never to
 * an action, so `Reports::fake()` sees every call.
 */
ArchPresets::modelsGoThroughTheFacade('RoundlyConsulting\Reports');
