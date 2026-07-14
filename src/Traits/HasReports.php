<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReportModel;

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
     * @return MorphMany<Report, $this>
     */
    public function pendingReports(): MorphMany
    {
        return $this->reports()->whereIn('status', array_map(
            static fn (Status $status): string => $status->value,
            Status::open(),
        ));
    }

    public function reportsCount(?Status $status = null): int
    {
        $query = $this->reports();

        if ($status instanceof Status) {
            $query->where('status', $status->value);
        }

        return $query->count();
    }

    public function hasBeenReported(): bool
    {
        return $this->reports()->exists();
    }

    public function isReportedBy(Model $reporter): bool
    {
        return $this->reports()
            ->where('reporter_type', $reporter->getMorphClass())
            ->where('reporter_id', $reporter->getKey())
            ->exists();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithReportCounts(Builder $query): Builder
    {
        return $query->withCount('reports');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMostReported(Builder $query, ?Status $onlyStatus = null): Builder
    {
        return $query->withCount(['reports' => fn (Builder $reports): Builder => $this->constrainByStatus($reports, $onlyStatus)])
            ->orderByDesc('reports_count');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeReportedMoreThan(Builder $query, int $threshold, ?Status $onlyStatus = null): Builder
    {
        return $query->withCount(['reports' => fn (Builder $reports): Builder => $this->constrainByStatus($reports, $onlyStatus)])
            ->groupBy($this->getQualifiedKeyName())
            ->having('reports_count', '>', $threshold);
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    private function constrainByStatus(Builder $query, ?Status $onlyStatus): Builder
    {
        if ($onlyStatus instanceof Status) {
            return $query->where('status', $onlyStatus->value);
        }

        return $query;
    }

    /**
     * @return class-string<Report>
     */
    private function reportModel(): string
    {
        return ReportModel::class();
    }
}
