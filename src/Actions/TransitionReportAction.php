<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Reports\DataTransferObjects\ResolveReportData;
use RoundlyConsulting\Reports\Enums\Status;
use RoundlyConsulting\Reports\Events\ReportStatusChanged;
use RoundlyConsulting\Reports\Exceptions\InvalidStatusTransitionException;
use RoundlyConsulting\Reports\Models\Report;
use RoundlyConsulting\Reports\Support\ReportsConfig;

/**
 * Moves a report to a status in one locked step — the one place a report's status and
 * its resolution fields are written.
 *
 * Inside a transaction the row is re-read under a lock, and the move is checked against
 * that stored status rather than the caller's (possibly stale) copy; only then are the
 * status and the resolution written, together. So a refused move writes nothing, and
 * two concurrent decisions apply once: the second one finds the report already moved.
 *
 * - Into Resolved / Rejected with a decision: stamps the decider, the note and the time.
 * - Into an open status (a reopen, or back into review): clears the resolution.
 * - Into Closed: keeps the resolution it closes out.
 *
 * Fires ReportStatusChanged once the move is committed.
 *
 * @internal
 */
final class TransitionReportAction
{
    /**
     * @return Status|null the status the report moved from; null when it already was
     *                     on `$to` — then nothing is written and no event fires
     *
     * @throws InvalidStatusTransitionException when `reports.strict_transitions` is on and
     *                                          the Status graph does not allow the move
     */
    public function execute(Report $report, Status $to, ?ResolveReportData $decision = null): ?Status
    {
        $from = $report->getConnection()->transaction(function () use ($report, $to, $decision): ?Status {
            $this->syncWithLockedRow($report);

            $from = $report->status;

            if ($from === $to) {
                return null;
            }

            if (ReportsConfig::strictTransitions() && ! $from->canTransitionTo($to)) {
                throw InvalidStatusTransitionException::for($report, $from, $to);
            }

            $report->status = $to;

            if ($to->isOpen()) {
                $this->clearResolution($report);
            } elseif ($decision instanceof ResolveReportData && $to !== Status::Closed) {
                $this->stamp($report, $decision);
            }

            $report->save();

            return $from;
        });

        if ($from instanceof Status) {
            event(new ReportStatusChanged(
                report: $report,
                statusBefore: $from,
                statusNow: $to,
            ));
        }

        return $from;
    }

    /**
     * Lock the row and adopt what is stored for the fields this action owns, so the
     * decision is made on the committed status and a no-op returns the stored
     * resolution. The caller's other unsaved changes are left alone.
     */
    private function syncWithLockedRow(Report $report): void
    {
        if (! $report->exists) {
            return;
        }

        $stored = $report->newQueryWithoutScopes()
            ->whereKey($report->getKey())
            ->lockForUpdate()
            ->first();

        if (! $stored instanceof Report) {
            return;
        }

        $owned = ['status', 'resolved_by_id', 'resolved_by_type', 'resolution_note', 'resolved_at'];

        $report->setRawAttributes(array_replace($report->getAttributes(), Arr::only($stored->getAttributes(), $owned)));
        $report->syncOriginalAttributes($owned);
    }

    private function stamp(Report $report, ResolveReportData $decision): void
    {
        $report->resolved_by_id = $decision->resolver?->getKey();
        $report->resolved_by_type = $decision->resolver?->getMorphClass();
        $report->resolution_note = $decision->note;
        $report->resolved_at = Carbon::now();
    }

    private function clearResolution(Report $report): void
    {
        $report->resolved_by_id = null;
        $report->resolved_by_type = null;
        $report->resolution_note = null;
        $report->resolved_at = null;
    }
}
