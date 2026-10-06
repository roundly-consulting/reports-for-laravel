<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reports\Actions\CreateReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportThresholdReached;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\UnknownReportReasonException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Tests\PostTestModel;
use RoundlyConsulting\Reports\Tests\UserTestModel;

beforeEach(function (): void {
    $this->action = app(CreateReportAction::class);
    $this->post = PostTestModel::create();
    $this->user = UserTestModel::create();
});

it('creates a pending report with explicit fields', function (): void {
    $report = $this->action->execute(new CreateReportData(
        subject: $this->post,
        reason: Reason::Abuse,
        reporter: $this->user,
        description: 'Bad post.',
    ));

    expect($report)->toBeInstanceOf(Report::class)
        ->and($report->status)->toBe(Status::Pending)
        ->and($report->reason)->toBe('abuse')
        ->and($report->description)->toBe('Bad post.')
        ->and($report->reporter_id)->toBe($this->user->getKey())
        ->and($report->reported_id)->toBe($this->post->getKey());
});

it('throws for a disallowed reason', function (): void {
    $this->action->execute(new CreateReportData(
        subject: $this->post,
        reason: 'copyright',
        reporter: $this->user,
    ));
})->throws(UnknownReportReasonException::class);

it('permits unknown reasons when configured', function (): void {
    config()->set('reports.allow_unknown_reasons', true);

    $report = $this->action->execute(new CreateReportData(
        subject: $this->post,
        reason: 'copyright',
        reporter: $this->user,
    ));

    expect($report->reason)->toBe('copyright');
});

it('blocks a duplicate open report from the same reporter', function (): void {
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
})->throws(DuplicateReportException::class);

it('allows a second report once the first is resolved when scope is open', function (): void {
    $first = $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    $first->update(['status' => Status::Resolved]);

    $second = $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));

    expect($second->getKey())->not->toBe($first->getKey());
});

it('blocks even resolved duplicates when scope is any', function (): void {
    config()->set('reports.duplicate_scope', 'any');

    $first = $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    $first->update(['status' => Status::Resolved]);

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
})->throws(DuplicateReportException::class);

it('allows duplicates when prevention is disabled', function (): void {
    config()->set('reports.prevent_duplicates', false);

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));

    expect(Report::query()->count())->toBe(2);
});

it('dedupes guest reports on the guest identifier', function (): void {
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, guestIdentifier: 'guest-hash'));

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, guestIdentifier: 'guest-hash'));
})->throws(DuplicateReportException::class);

it('does not dedupe anonymous reports without a reporter or guest key', function (): void {
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam));
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam));

    expect(Report::query()->count())->toBe(2);
});

it('fires the threshold event exactly when the open count reaches the threshold', function (): void {
    config()->set('reports.threshold', 2);
    config()->set('reports.prevent_duplicates', false);
    Event::fake([ReportThresholdReached::class]);

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    Event::assertNotDispatched(ReportThresholdReached::class);

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    Event::assertDispatched(ReportThresholdReached::class, function (ReportThresholdReached $event): bool {
        return $event->subject->is($this->post) && $event->count === 2 && $event->threshold === 2;
    });

    // A third report goes past the threshold and must NOT fire again.
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    Event::assertDispatchedTimes(ReportThresholdReached::class, 1);
});

it('never fires the threshold event when threshold is null', function (): void {
    config()->set('reports.threshold', null);
    config()->set('reports.prevent_duplicates', false);
    Event::fake([ReportThresholdReached::class]);

    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));
    $this->action->execute(new CreateReportData(subject: $this->post, reason: Reason::Spam, reporter: $this->user));

    Event::assertNotDispatched(ReportThresholdReached::class);
});

it('refuses an unsaved subject without writing a row', function (): void {
    $keyless = new PostTestModel;
    $keyless->exists = true;

    // An unsaved subject used to file a report with reported_id NULL — an orphan no subject
    // finds — and the next one on another unsaved model of the class was a "duplicate".
    expect(fn () => $this->action->execute(new CreateReportData(subject: new PostTestModel, reason: 'spam', reporter: $this->user)))
        ->toThrow(LogicException::class, 'A report requires a saved subject.')
        ->and(fn () => $this->action->execute(new CreateReportData(subject: new PostTestModel, reason: 'spam', reporter: $this->user)))
        ->toThrow(LogicException::class, 'A report requires a saved subject.')
        ->and(fn () => $this->action->execute(new CreateReportData(subject: $keyless, reason: 'spam')))
        ->toThrow(LogicException::class, 'A report requires a saved subject.');

    expect(Report::query()->count())->toBe(0);
});

it('refuses an unsaved reporter without writing a row', function (): void {
    expect(fn () => $this->action->execute(new CreateReportData(subject: $this->post, reason: 'spam', reporter: new UserTestModel)))
        ->toThrow(LogicException::class, 'A report requires a saved reporter.');

    expect(Report::query()->count())->toBe(0);
});
