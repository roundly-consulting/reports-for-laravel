<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests;

use Illuminate\Database\Eloquent\Model;

/**
 * A user that does not use the approvals GivesApprovals trait — the "non-approver" actor
 * of the moderation authorization tests.
 */
class PlainUserTestModel extends Model
{
    public $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
