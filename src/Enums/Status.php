<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Enums;

enum Status: string
{
    case New = 'New';
    case Solving = 'Solving';
    case Closed = 'Closed';
}
