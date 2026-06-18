<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reports\Actions\CreateReportAction;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\PendingReport;
use RoundlyConsulting\Reports\Support\ReasonRegistry;

trait GivesReports
{
    /**
     * @return MorphMany<Report, $this>
     */
    public function givenReports(): MorphMany
    {
        return $this->morphMany($this->reportModel(), 'reporter');
    }

    public function giveReportTo(Model $model, ?string $description = null, Reason|string|null $reason = null): Report
    {
        /** @var ReasonRegistry $reasons */
        $reasons = app(ReasonRegistry::class);

        /** @var CreateReportAction $action */
        $action = app(CreateReportAction::class);

        return $action->execute(new CreateReportData(
            subject: $model,
            reason: $reason ?? $reasons->default(),
            reporter: $this,
            description: $description,
        ));
    }

    public function report(Model $model): PendingReport
    {
        /** @var CreateReportAction $action */
        $action = app(CreateReportAction::class);

        /** @var ReasonRegistry $reasons */
        $reasons = app(ReasonRegistry::class);

        return (new PendingReport($action, $reasons))->about($model)->by($this);
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
