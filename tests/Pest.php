<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Tests\Fixtures\SwappedReportTestCase;
use RoundlyConsulting\Reports\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different
// base case, and a blanket bind would claim it first. ArchTest.php is listed because
// `swappableModelsAreNotFinal` reads the `reports.model` config default and so needs the
// app booted — an arch file is not automatically test-cased.
uses(TestCase::class)->in('ArchTest.php', 'Feature', 'Unit');

// The model-swap proof needs `reports.model` pointed at the host subclass BEFORE the
// providers boot, so it runs on its own base case in its own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedReportTestCase::class)->in('ModelSwap');
