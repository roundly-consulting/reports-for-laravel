<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;

it('reports when there are no open reports', function (): void {
    Report::factory()->resolved()->create();

    $this->artisan('reports:recount')
        ->expectsOutputToContain('No subjects with open reports.')
        ->assertExitCode(0);
});

it('lists per-subject open report counts', function (): void {
    $post = PostTestModel::create();
    Report::factory()->against($post)->pending()->count(3)->create();

    $this->artisan('reports:recount')
        ->assertExitCode(0);
});

it('applies a minimum threshold filter', function (): void {
    $post = PostTestModel::create();
    Report::factory()->against($post)->pending()->count(1)->create();

    $this->artisan('reports:recount', ['--threshold' => 5])
        ->expectsOutputToContain('No subjects with open reports.')
        ->assertExitCode(0);
});

it('refuses a threshold that is not a whole number', function (string $threshold): void {
    $post = PostTestModel::create();
    Report::factory()->against($post)->pending()->count(2)->create();

    // A blunt (int) cast read "abc" and "-3" as "list everything" and "2.9" as 2.
    $this->artisan('reports:recount', ['--threshold' => $threshold])
        ->expectsOutputToContain('--threshold must be a whole number (0 or more).')
        ->doesntExpectOutputToContain('Open reports')
        ->assertExitCode(1);
})->with(['abc', '-3', '2.9']);

it('lists only the subjects at or above a whole-number threshold', function (): void {
    $busy = PostTestModel::create();
    $quiet = PostTestModel::create();
    Report::factory()->against($busy)->pending()->count(2)->create();
    Report::factory()->against($quiet)->pending()->create();

    $this->artisan('reports:recount', ['--threshold' => '2'])
        ->expectsTable(
            ['Subject type', 'Subject id', 'Open reports'],
            [[$busy->getMorphClass(), (string) $busy->getKey(), '2']],
        )
        ->assertExitCode(0);
});
