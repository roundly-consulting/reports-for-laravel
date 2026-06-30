<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Enums;

use RoundlyConsulting\Enums\Helpers;

enum Reason: string
{
    use Helpers;

    case Spam = 'spam';
    case Abuse = 'abuse';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Misinformation = 'misinformation';
    case Other = 'other';
}
