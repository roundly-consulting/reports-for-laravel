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
