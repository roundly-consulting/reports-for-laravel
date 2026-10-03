<?php

declare(strict_types=1);

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reports\Support\ReasonRegistry;

beforeEach(function (): void {
    $this->registry = app(ReasonRegistry::class);
});

it('returns all configured reason slugs as a list', function (): void {
    expect($this->registry->all())
        ->toBe(['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other']);
});

it('uses the enum cases when the reasons are absent (strict config)', function (): void {
    config()->set('reports.reasons', null);

    expect($this->registry->all())
        ->toBe(['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other']);
});

it('refuses an empty or junk reason list instead of using the enum cases (strict config)', function (mixed $value): void {
    config()->set('reports.reasons', $value);

    expect(fn () => $this->registry->all())->toThrow(InvalidConfigurationException::class, 'reports.reasons');
})->with([
    'empty' => [[]],
    'a string' => ['spam,abuse'],
    'a blank slug' => [['spam', '']],
    'a non-string slug' => [['spam', 7]],
]);

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

it('uses the Other reason when the default is absent (strict config)', function (): void {
    config()->set('reports.default_reason', null);

    expect($this->registry->default())->toBe('other');
});

it('refuses a blank or non-string default reason (strict config)', function (mixed $value): void {
    config()->set('reports.default_reason', $value);

    expect(fn () => $this->registry->default())
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [reports.default_reason] must be a non-empty string');
})->with(['blank' => [''], 'an array' => [['spam']]]);

it('labels known enum slugs and custom slugs', function (): void {
    expect($this->registry->label('abuse'))->toBe('Abuse')
        ->and($this->registry->label('copyright'))->toBe('copyright');
});
