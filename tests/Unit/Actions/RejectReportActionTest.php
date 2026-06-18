<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Actions\RejectReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportRejected;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    $this->action = app(RejectReportAction::class);
});

it('rejects a report with a note and fires ReportRejected', function (): void {
    Event::fake([ReportRejected::class]);

    $admin = UserTestModel::create();
    $report = Report::factory()->pending()->create();

    $rejected = $this->action->execute($report, new ResolveReportData(resolver: $admin, note: 'Not a violation.'));

    expect($rejected->status)->toBe(Status::Rejected)
        ->and($rejected->resolution_note)->toBe('Not a violation.')
        ->and($rejected->resolved_by_id)->toBe($admin->getKey());

    Event::assertDispatched(ReportRejected::class);
});
