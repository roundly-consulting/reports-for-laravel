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
use RoundlyConsulting\Approvals\Interfaces\RequiresApprovalInterface;
use RoundlyConsulting\Approvals\Models\ApprovalRequest;
use RoundlyConsulting\Approvals\Traits\RequiresApproval;
use RoundlyConsulting\Reports\Database\Factories\ReportFactory;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportCreated;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;

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
 */
final class Report extends Model implements RequiresApprovalInterface
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

    public function changeStatusTo(Status $status): self
    {
        if ($this->status === $status) {
            return $this;
        }

        if ($this->strictTransitions() && ! $this->status->canTransitionTo($status)) {
            throw InvalidStatusTransitionException::for($this, $this->status, $status);
        }

        $previousStatus = $this->status;

        $this->update([
            'status' => $status,
        ]);

        event(new ReportStatusChanged(
            report: $this,
            statusBefore: $previousStatus,
            statusNow: $this->status,
        ));

        return $this;
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

    private function strictTransitions(): bool
    {
        return (bool) config('reports.strict_transitions', true);
    }
}
