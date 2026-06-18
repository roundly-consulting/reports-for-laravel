<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reports\Enums;

enum Reason: string
{
    case Spam = 'spam';
    case Abuse = 'abuse';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Misinformation = 'misinformation';
    case Other = 'other';

    /**
     * The human-friendly, translatable label for this reason.
     */
    public function label(): string
    {
        $key = "reports::reasons.{$this->value}";
        $translation = trans($key);

        return is_string($translation) && $translation !== $key
            ? $translation
            : ucfirst($this->value);
    }
}
