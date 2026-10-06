<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;
use RoundlyConsulting\Reports\Enums\Reason;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\ReportsManager;
use RoundlyConsulting\Reports\Support\PendingReport;
use RoundlyConsulting\Reports\Traits\Concerns\ResolvesReportModel;

trait GivesReports
{
    use ResolvesReportModel;

    /**
     * @return MorphMany<Report, $this>
     */
    public function givenReports(): MorphMany
    {
        return $this->morphMany($this->reportModel(), 'reporter');
    }

    /**
     * File a report about the model as this reporter (through the manager, so the
     * fake records it).
     */
    public function giveReportTo(Model $model, ?string $description = null, Reason|string|null $reason = null): Report
    {
        $reports = app(ReportsManager::class);

        return $reports->create(new CreateReportData(
            subject: $model,
            reason: $reason ?? $reports->defaultReason(),
            reporter: $this,
            description: $description,
        ));
    }

    /**
     * Start a fluent report about the model, filed by this reporter.
     */
    public function report(Model $model): PendingReport
    {
        return app(ReportsManager::class)->report($model)->by($this);
    }
}
