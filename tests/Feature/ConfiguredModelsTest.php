<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\Models\CustomReport;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    config()->set('reports.model', CustomReport::class);
});

it('files a report through the host model', function (): void {
    $user = UserTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $report = Reports::report($post)->by($user)->for(Reason::Abuse)->create();

    expect($report)->toBeInstanceOf(CustomReport::class)
        ->and($report->reported_id)->toBe($post->getKey());
});

it('reads the host model back through the traits', function (): void {
    $user = UserTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $user->giveReportTo($post);

    expect($post->reports()->first())->toBeInstanceOf(CustomReport::class)
        ->and($post->reportsCount())->toBe(1)
        ->and($post->hasBeenReported())->toBeTrue()
        ->and($post->isReportedBy($user))->toBeTrue()
        ->and($user->givenReports()->first())->toBeInstanceOf(CustomReport::class);
});

it('resolves a host report and dedupes against it', function (): void {
    $user = UserTestModel::query()->create();
    $post = PostTestModel::query()->create();

    $report = $user->giveReportTo($post);

    Reports::resolve($report, $user, 'handled');

    expect($report->refresh()->status)->toBe(Status::Resolved);

    // The duplicate guard queries through the same seam, so the resolved
    // (terminal) report no longer blocks a fresh one under the open scope.
    $second = $user->giveReportTo($post);

    expect($second)->toBeInstanceOf(CustomReport::class);
});

it('prunes and recounts host reports from the console', function (): void {
    $post = PostTestModel::query()->create();

    CustomReport::factory()->against($post)->create();
    CustomReport::factory()->against($post)->count(2)->create();

    $this->artisan('reports:recount')->assertSuccessful();

    $old = CustomReport::factory()->against($post)->status(Status::Resolved)->create();
    $old->forceFill(['created_at' => Carbon::now()->subDays(90)])->save();

    $this->artisan('reports:prune', ['--days' => 30])->assertSuccessful();

    expect(CustomReport::withTrashed()->find($old->getKey())?->trashed())->toBeTrue()
        ->and(Report::query()->count())->toBe(3);
});
