<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RoundlyConsulting\Reports\Database\Factories\ReportFactory;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportCreated;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;

/**
 * @property int $id
 * @property int $reporter_id
 * @property string $reporter_type
 * @property int|null $reported_id
 * @property string|null $reported_type
 * @property Status $status
 * @property string $type
 * @property string $description
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property CarbonInterface|null $deleted_at
 * @property-read Model $reporter
 * @property-read Model $reported
 */
final class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $guarded = [];

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => ReportCreated::class,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => Status::class,
        ];
    }

    public function changeStatusTo(Status $status): self
    {
        if ($this->status === $status) {
            return $this;
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

    protected static function newFactory(): ReportFactory
    {
        return ReportFactory::new();
    }
}
