<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Reason;

it('derives a readable label for each case from the enums trait', function (): void {
    expect(Reason::Abuse->label())->toBe('Abuse')
        ->and(Reason::Inappropriate->label())->toBe('Inappropriate')
        ->and(Reason::Spam->readable())->toBe('Spam');
});

it('exposes the enums collection helpers', function (): void {
    expect(Reason::values()->all())->toBe(['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other'])
        ->and(Reason::validationRule())->toBe('in:spam,abuse,harassment,inappropriate,misinformation,other')
        ->and(Reason::toOptions()->get('abuse'))->toBe('Abuse');
});

it('resolves cases by name', function (): void {
    expect(Reason::fromName('Harassment'))->toBe(Reason::Harassment)
        ->and(Reason::tryFromName('nope'))->toBeNull();
});
