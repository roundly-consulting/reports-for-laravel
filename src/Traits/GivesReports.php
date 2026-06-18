<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;

trait GivesReports
{
    /**
     * @return MorphMany<Report, $this>
     */
    public function givenReports(): MorphMany
    {
        return $this->morphMany($this->reportModel(), 'reporter');
    }

    public function giveReportTo(Model $model, string $description, string $type = 'default'): Report
    {
        /** @var Report $report */
        $report = $this->givenReports()->create([
            'reported_id' => $model->getKey(),
            'reported_type' => $model->getMorphClass(),
            'type' => $type,
            'description' => $description,
            'status' => Status::New,
        ]);

        return $report;
    }

    /**
     * @return class-string<Report>
     */
    private function reportModel(): string
    {
        /** @var class-string<Report> $model */
        $model = config('reports.model', Report::class);

        return $model;
    }
}
