<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;

/**
 * Fluent report builder returned by `Reports::report()` / `Reports::from()` and the
 * GivesReports trait. `create()` goes through the manager, so host overrides and
 * `Reports::fake()` see every report filed this way.
 */
final class PendingReport
{
    private ?Model $reporter = null;

    private ?string $guestIdentifier = null;

    private ?string $description = null;

    private Reason|string|null $reason = null;

    public function __construct(
        private readonly ReportsManager $manager,
        private ?Model $subject = null,
    ) {}

    public function about(Model $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function by(Model $reporter): self
    {
        $this->reporter = $reporter;
        $this->guestIdentifier = null;

        return $this;
    }

    public function asGuest(string $identifier): self
    {
        $this->guestIdentifier = $identifier;
        $this->reporter = null;

        return $this;
    }

    public function for(Reason|string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    public function reason(Reason|string $reason): self
    {
        return $this->for($reason);
    }

    public function because(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function create(): Report
    {
        if (! $this->subject instanceof Model) {
            throw new \LogicException('A report requires a subject. Call Reports::report($subject) first.');
        }

        return $this->manager->create(new CreateReportData(
            subject: $this->subject,
            reason: $this->reason ?? $this->manager->defaultReason(),
            reporter: $this->reporter,
            description: $this->description,
            guestIdentifier: $this->guestIdentifier,
        ));
    }
}
