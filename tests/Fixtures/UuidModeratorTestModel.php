<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;

/**
 * A moderator with a UUID primary key.
 */
class UuidModeratorTestModel extends Model implements GivesApprovalsInterface
{
    use GivesApprovals;
    use HasUuids;

    public $table = 'uuid_moderators';

    protected $guarded = [];

    public $timestamps = false;
}
