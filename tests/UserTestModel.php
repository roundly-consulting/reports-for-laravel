<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Traits\GivesReports;

class UserTestModel extends Model
{
    use GivesReports;

    public $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
