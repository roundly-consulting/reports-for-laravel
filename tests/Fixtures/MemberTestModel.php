<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Reports\Contracts\Reporter;
use RoundlyConsulting\Reports\Traits\GivesReports;
use RoundlyConsulting\Reports\Traits\HasReports;

/**
 * A model on both sides of a report — users reporting users. Both traits on one class
 * used to be a fatal trait collision.
 */
class MemberTestModel extends Model implements Reportable, Reporter
{
    use GivesReports;
    use HasReports;

    public $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
