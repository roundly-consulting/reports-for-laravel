<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Status;

it('exposes a transition table for every status', function (): void {
    $transitions = Status::transitions();

    foreach (Status::cases() as $status) {
        expect($transitions)->toHaveKey($status->value);
    }
});

it('lists allowed transitions for a status', function (): void {
    expect(Status::Pending->allowedTransitions())
        ->toBe([Status::InReview, Status::Resolved, Status::Rejected]);
});

it('allows declared transitions', function (): void {
    expect(Status::Pending->canTransitionTo(Status::InReview))->toBeTrue()
        ->and(Status::InReview->canTransitionTo(Status::Resolved))->toBeTrue()
        ->and(Status::Resolved->canTransitionTo(Status::Closed))->toBeTrue()
        ->and(Status::Rejected->canTransitionTo(Status::Pending))->toBeTrue()
        ->and(Status::Closed->canTransitionTo(Status::Pending))->toBeTrue();
});

it('rejects undeclared transitions', function (): void {
    expect(Status::Resolved->canTransitionTo(Status::InReview))->toBeFalse()
        ->and(Status::Pending->canTransitionTo(Status::Closed))->toBeFalse()
        ->and(Status::Rejected->canTransitionTo(Status::Resolved))->toBeFalse();
});

it('knows which statuses are terminal', function (): void {
    expect(Status::Resolved->isTerminal())->toBeTrue()
        ->and(Status::Rejected->isTerminal())->toBeTrue()
        ->and(Status::Closed->isTerminal())->toBeTrue()
        ->and(Status::Pending->isTerminal())->toBeFalse()
        ->and(Status::InReview->isTerminal())->toBeFalse();
});

it('knows which statuses are open', function (): void {
    expect(Status::Pending->isOpen())->toBeTrue()
        ->and(Status::InReview->isOpen())->toBeTrue()
        ->and(Status::Resolved->isOpen())->toBeFalse();

    expect(Status::open())->toBe([Status::Pending, Status::InReview]);
});

it('exposes the enums collection helpers', function (): void {
    expect(Status::values()->all())->toBe(['pending', 'in_review', 'resolved', 'rejected', 'closed'])
        ->and(Status::validationRule())->toBe('in:pending,in_review,resolved,rejected,closed')
        ->and(Status::InReview->readable())->toBe('In Review')
        ->and(Status::toOptions()->get('resolved'))->toBe('Resolved');
});
