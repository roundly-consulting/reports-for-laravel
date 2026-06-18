<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Exceptions\DuplicateReportException;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Exceptions\ReportsException;
use RoundlyConsulting\Reports\Exceptions\UnknownReportReasonException;
use RoundlyConsulting\Reports\Models\Report;

it('builds an invalid-transition exception carrying context', function (): void {
    $report = Report::factory()->create();

    $exception = InvalidStatusTransitionException::for($report, Status::Resolved, Status::InReview);

    expect($exception)->toBeInstanceOf(ReportsException::class)
        ->and($exception->report->is($report))->toBeTrue()
        ->and($exception->from)->toBe(Status::Resolved)
        ->and($exception->to)->toBe(Status::InReview)
        ->and($exception->getMessage())->toContain('resolved')
        ->and($exception->getMessage())->toContain('in_review');
});

it('builds a duplicate-report exception carrying the existing report', function (): void {
    $report = Report::factory()->create();

    $exception = DuplicateReportException::for($report);

    expect($exception)->toBeInstanceOf(ReportsException::class)
        ->and($exception->existing->is($report))->toBeTrue()
        ->and($exception->getMessage())->toContain((string) $report->getKey());
});

it('builds an unknown-reason exception carrying the slug and allowed list', function (): void {
    $exception = UnknownReportReasonException::slug('copyright', ['spam', 'abuse']);

    expect($exception)->toBeInstanceOf(ReportsException::class)
        ->and($exception->slug)->toBe('copyright')
        ->and($exception->allowed)->toBe(['spam', 'abuse'])
        ->and($exception->getMessage())->toContain('copyright')
        ->and($exception->getMessage())->toContain('spam');
});
