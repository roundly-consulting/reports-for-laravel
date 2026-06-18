<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reports\Models\Report;

trait HasReports
{
    /**
     * @return MorphMany<Report, $this>
     */
    public function reports(): MorphMany
    {
        return $this->morphMany($this->reportModel(), 'reported');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMostReported(Builder $query): Builder
    {
        return $query->withCount('reports')
            ->orderByDesc('reports_count');
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
