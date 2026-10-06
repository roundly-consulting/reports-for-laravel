<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReportModel;

/** @extends Factory<Report> */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * Build the model the host configured, not the packaged one: a host that points
     * `reports.model` at its own subclass gets that subclass, with its casts and events,
     * out of the factory the package ships.
     *
     * @return class-string<Report>
     */
    public function modelName(): string
    {
        return ReportModel::class();
    }

    /** @return array<model-property<Report>, mixed> */
    public function definition(): array
    {
        return [
            'reporter_id' => null,
            'reporter_type' => null,
            'reported_id' => null,
            'reported_type' => null,
            'status' => Status::Pending,
            'reason' => Reason::Spam->value,
            'description' => $this->faker->sentence(),
            'guest_identifier' => null,
        ];
    }

    public function forReporter(Model $reporter): self
    {
        return $this->state(fn (): array => [
            'reporter_id' => $reporter->getKey(),
            'reporter_type' => $reporter->getMorphClass(),
        ]);
    }

    public function against(Model $subject): self
    {
        return $this->state(fn (): array => [
            'reported_id' => $subject->getKey(),
            'reported_type' => $subject->getMorphClass(),
        ]);
    }

    public function reason(Reason|string $reason): self
    {
        $value = $reason instanceof Reason ? $reason->value : $reason;

        return $this->state(fn (): array => ['reason' => $value]);
    }

    public function status(Status $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function pending(): self
    {
        return $this->status(Status::Pending);
    }

    public function inReview(): self
    {
        return $this->status(Status::InReview);
    }

    public function resolved(?Model $by = null): self
    {
        return $this->state(fn (): array => [
            'status' => Status::Resolved,
            'resolved_by_id' => $by?->getKey(),
            'resolved_by_type' => $by?->getMorphClass(),
            'resolved_at' => Carbon::now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'status' => Status::Rejected,
            'resolved_at' => Carbon::now(),
        ]);
    }

    public function closed(): self
    {
        return $this->status(Status::Closed);
    }

    public function guest(?string $identifier = null): self
    {
        return $this->state(fn (): array => [
            'reporter_id' => null,
            'reporter_type' => null,
            'guest_identifier' => $identifier ?? hash('sha256', (string) $this->faker->ipv4()),
        ]);
    }
}
