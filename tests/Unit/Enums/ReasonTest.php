<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Enums\Reason;

it('returns a translated label for each case', function (): void {
    expect(Reason::Abuse->label())->toBe('Abuse')
        ->and(Reason::Inappropriate->label())->toBe('Inappropriate content')
        ->and(Reason::Spam->label())->toBe('Spam');
});

it('falls back to a humanised slug when no translation exists', function (): void {
    app('translator')->setLoaded([]);
    app()->setLocale('xx');

    expect(Reason::Misinformation->label())->toBe('Misinformation');
});
