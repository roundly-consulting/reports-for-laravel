<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Enums;

use RoundlyConsulting\Enums\Helpers;

enum Status: string
{
    use Helpers;

    case Pending = 'pending';
    case InReview = 'in_review';
    case Resolved = 'resolved';
    case Rejected = 'rejected';
    case Closed = 'closed';

    /**
     * The allowed next states for each status.
     *
     * @return array<string, list<self>>
     */
    public static function transitions(): array
    {
        return [
            self::Pending->value => [self::InReview, self::Resolved, self::Rejected],
            self::InReview->value => [self::Resolved, self::Rejected, self::Pending],
            self::Resolved->value => [self::Closed, self::Pending],
            self::Rejected->value => [self::Pending],
            self::Closed->value => [self::Pending],
        ];
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return self::transitions()[$this->value];
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), strict: true);
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Resolved, self::Rejected, self::Closed => true,
            self::Pending, self::InReview => false,
        };
    }

    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    /**
     * The statuses that count as open / non-terminal.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, self::InReview];
    }
}
