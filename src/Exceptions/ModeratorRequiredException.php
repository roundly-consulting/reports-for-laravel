<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Models\Report;
use Throwable;

/**
 * The report is under moderation, so only one of the moderators its moderation request
 * names (or a delegate of one) may settle it. Thrown for an outsider, for a call with no
 * actor, and for a raw status move out of the open statuses. Nothing is written.
 */
final class ModeratorRequiredException extends ReportsException
{
    public function __construct(
        public readonly Report $report,
        public readonly ?Model $actor,
        string $message,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * The actor is not a moderator of the report's open moderation request. `$previous`
     * is the approvals engine's refusal, when it made the call.
     */
    public static function notAModerator(Report $report, Model $actor, ?Throwable $previous = null): self
    {
        $key = $actor->getKey();
        $who = $actor->getMorphClass().'#'.(is_int($key) || is_string($key) ? $key : '?');

        return new self(
            $report,
            $actor,
            "The actor [{$who}] is not a moderator of report [{$report->getKey()}], which is under moderation.",
            $previous,
        );
    }

    /**
     * No actor was given (a system call or a raw status move) while the report is under
     * moderation.
     */
    public static function withoutActor(Report $report): self
    {
        return new self(
            $report,
            null,
            "Report [{$report->getKey()}] is under moderation: only one of its moderators can resolve or reject it.",
        );
    }
}
