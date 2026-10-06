<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use RoundlyConsulting\Reports\DataTransferObjects\CreateReportData;

/**
 * A report is filed against a saved subject, by a saved reporter (or by a guest, or
 * anonymously). An unsaved model has no key: the report would store a NULL id that no
 * relation ever finds, and the duplicate lookup would read NULL as "any unsaved model of
 * the class". Shared by CreateReportAction and the fake.
 *
 * @internal
 */
final class FilingGuard
{
    /**
     * @throws LogicException for an unsaved subject or reporter
     */
    public static function ensureSaved(CreateReportData $data): void
    {
        if (! self::saved($data->subject)) {
            throw new LogicException('A report requires a saved subject.');
        }

        if ($data->reporter !== null && ! self::saved($data->reporter)) {
            throw new LogicException('A report requires a saved reporter.');
        }
    }

    private static function saved(Model $model): bool
    {
        return $model->exists && $model->getKey() !== null;
    }
}
