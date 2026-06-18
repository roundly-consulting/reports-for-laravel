<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

/** @extends Factory<Report> */
final class ReportFactory extends Factory
{
    protected $model = Report::class;

    /** @return array<model-property<Report>, mixed> */
    public function definition(): array
    {
        return [
            'reporter_id' => $this->faker->randomNumber(),
            'reporter_type' => 'reporter',
            'reported_id' => $this->faker->randomNumber(),
            'reported_type' => 'reported',
            'status' => Status::New,
            'type' => 'default',
            'description' => $this->faker->sentence(),
        ];
    }

    public function status(Status $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
