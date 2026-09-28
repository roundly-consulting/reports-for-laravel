<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Facades\Reports;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    config()->set('reports.prevent_duplicates', false);
    config()->set('reports.threshold', 3);
});

it('fires again when the open count drops below the threshold and climbs back', function (): void {
    Event::fake([ReportThresholdReached::class]);
    $post = PostTestModel::create();

    $reports = [];
    foreach (range(1, 3) as $i) {
        $reports[] = Reports::report($post)->by(UserTestModel::create())->for(Reason::Spam)->create();
    }
    Event::assertDispatchedTimes(ReportThresholdReached::class, 1);

    // Settling one drops the open count to 2; the next filing crosses 3 again.
    Reports::resolve($reports[0]);
    Reports::report($post)->by(UserTestModel::create())->for(Reason::Spam)->create();

    Event::assertDispatchedTimes(ReportThresholdReached::class, 2);
});

it('fires the threshold event once on crossing and not afterwards', function (): void {
    Event::fake([ReportThresholdReached::class]);
    $post = PostTestModel::create();

    foreach (range(1, 2) as $i) {
        Reports::report($post)->by(UserTestModel::create())->for(Reason::Spam)->create();
    }
    Event::assertNotDispatched(ReportThresholdReached::class);

    Reports::report($post)->by(UserTestModel::create())->for(Reason::Spam)->create();
    Event::assertDispatchedTimes(ReportThresholdReached::class, 1);

    Reports::report($post)->by(UserTestModel::create())->for(Reason::Spam)->create();
    Event::assertDispatchedTimes(ReportThresholdReached::class, 1);
});
