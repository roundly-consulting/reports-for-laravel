<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Support\ReasonRegistry;

beforeEach(function (): void {
    $this->registry = app(ReasonRegistry::class);
});

it('returns all configured reason slugs as a list', function (): void {
    expect($this->registry->all())
        ->toBe(['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other']);
});

it('falls back to enum cases when reasons config is empty', function (): void {
    config()->set('reports.reasons', []);

    expect($this->registry->all())
        ->toBe(['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other']);
});

it('treats configured slugs as allowed', function (): void {
    expect($this->registry->isAllowed('spam'))->toBeTrue()
        ->and($this->registry->isAllowed('copyright'))->toBeFalse();
});

it('accepts custom slugs added to config', function (): void {
    config()->set('reports.reasons', ['spam', 'copyright']);

    expect($this->registry->isAllowed('copyright'))->toBeTrue();
});

it('allows any slug when unknown reasons are permitted', function (): void {
    config()->set('reports.allow_unknown_reasons', true);

    expect($this->registry->isAllowed('anything-goes'))->toBeTrue()
        ->and($this->registry->allowsUnknown())->toBeTrue();
});

it('returns the configured default reason', function (): void {
    expect($this->registry->default())->toBe('other');
});

it('falls back to the Other reason when default is blank', function (): void {
    config()->set('reports.default_reason', '');

    expect($this->registry->default())->toBe('other');
});

it('labels known enum slugs and custom slugs', function (): void {
    expect($this->registry->label('abuse'))->toBe('Abuse')
        ->and($this->registry->label('copyright'))->toBe('copyright');
});
