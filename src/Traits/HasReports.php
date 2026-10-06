<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Traits\Concerns\ResolvesReportModel;

trait HasReports
{
    use ResolvesReportModel;

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
     * Subjects with more than $threshold reports, optionally counting only one status.
     *
     * The filter goes through `whereHas`, not `having('reports_count', …)`.
     * `withCount()` compiles to a correlated **subquery** aliased `reports_count`, and a
     * select alias is not visible to `HAVING` on a standards-following engine — HAVING is
     * evaluated before the select list exists. SQLite resolves the alias anyway and
     * Postgres raises `column "reports_count" does not exist`, so this scope threw on
     * every real engine while its tests stayed green.
     *
     * The `groupBy` that stood here went with it: it was a leftover from a JOIN-shaped
     * mental model. A correlated subquery aggregates nothing in the outer query, so there
     * was never a group to form — and grouping by the key alone would itself be invalid
     * on Postgres beside a `select *`.
     *
     * `withCount` stays so the caller still gets the `reports_count` attribute.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeReportedMoreThan(Builder $query, int $threshold, ?Status $onlyStatus = null): Builder
    {
        return $query->withCount(['reports' => fn (Builder $reports): Builder => $this->constrainByStatus($reports, $onlyStatus)])
            ->whereHas(
                'reports',
                fn (Builder $reports): Builder => $this->constrainByStatus($reports, $onlyStatus),
                '>',
                $threshold,
            );
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
}
