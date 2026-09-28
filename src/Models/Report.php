<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Approvals\Enums\ApprovalStatus;
use RoundlyConsulting\Approvals\Interfaces\RequiresApprovalInterface;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Approvals\Traits\RequiresApproval;
use RoundlyConsulting\Reports\Database\Factories\ReportFactory;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportCreated;
use RoundlyConsulting\Reports\ReportsManager;

/**
 * @property int $id
 * @property int|string|null $reporter_id
 * @property string|null $reporter_type
 * @property int|string|null $reported_id
 * @property string|null $reported_type
 * @property int|string|null $resolved_by_id
 * @property string|null $resolved_by_type
 * @property Status $status
 * @property string $reason
 * @property string|null $description
 * @property string|null $resolution_note
 * @property string|null $guest_identifier
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Model|null $reporter
 * @property-read Model|null $reported
 * @property-read Model|null $resolvedBy
 * @property-read Collection<int, ApprovalRequest> $approvalRequests
 *
 * Deliberately not `final`: `config('reports.model')` documents pointing the
 * package at your own subclass, which `final` made impossible.
 */
class Report extends Model implements RequiresApprovalInterface
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    use RequiresApproval;
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => ReportCreated::class,
    ];

    public function getTable(): string
    {
        $table = config('reports.table');

        return is_string($table) ? $table : 'reports';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Move the report to a status — goes through the manager
     * (`Reports::changeStatus()`), so the fake records it.
     */
    public function changeStatusTo(Status $status): static
    {
        app(ReportsManager::class)->changeStatus($this, $status);

        return $this;
    }

    /**
     * Whether a moderation request (an approvals request on this report) is still open.
     * While it is, only the moderators it names settle the report.
     */
    public function isUnderModeration(): bool
    {
        return $this->approvalRequests()
            ->where('status', ApprovalStatus::Pending->value)
            ->exists();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reporter(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reported(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function resolvedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeWithStatus(Builder $query, Status $status): Builder
    {
        return $query->where('status', $status->value);
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeWithReason(Builder $query, string $reason): Builder
    {
        return $query->where('reason', $reason);
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', Status::Pending->value);
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', Status::Resolved->value);
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', Status::Rejected->value);
    }

    /**
     * @param  Builder<Report>  $query
     * @return Builder<Report>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            static fn (Status $status): string => $status->value,
            Status::open(),
        ));
    }

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
    }
}
