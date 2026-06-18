<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reports\Contracts\Reportable;
use RoundlyConsulting\Reports\Traits\HasReports;

class PostTestModel extends Model implements Reportable
{
    use HasReports;

    public $table = 'posts';

    protected $guarded = [];

    public $timestamps = false;
}
