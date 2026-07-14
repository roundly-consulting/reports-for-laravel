<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Every `reports.*` config key named by a string literal in the given PHP source.
 *
 * Reads **tokens**, not raw text: a docblock mentioning a key is documentation,
 * not a read, and a raw-text scrape would let a shipped-but-dead key pass
 * vacuously.
 *
 * @return list<string>
 */
function configKeysReadIn(string $file): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $keys = [];

    foreach ($tokens as $token) {
        if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $literal = trim($token[1], "'\"");

        if (str_starts_with($literal, 'reports.')) {
            $keys[] = substr($literal, strlen('reports.'));
        }
    }

    return array_values(array_unique($keys));
}

/**
 * @return list<string>
 */
function packageSourceFiles(string $directory): array
{
    $files = [];

    /** @var Iterator<SplFileInfo> $iterator */
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * Every leaf key path in the shipped config file. A list value (e.g. `reasons`)
 * is a leaf, not a nested section, so it is not flattened into `reasons.0`.
 *
 * @param  array<string, mixed>  $config
 * @return list<string>
 */
function flattenConfigKeys(array $config, string $prefix = ''): array
{
    $keys = [];

    foreach ($config as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            $keys = [...$keys, ...flattenConfigKeys($value, $path)];

            continue;
        }

        $keys[] = $path;
    }

    return $keys;
}

/**
 * @return list<string>
 */
function shippedConfigKeys(): array
{
    /** @var array<string, mixed> $config */
    $config = require __DIR__.'/../../config/reports.php';

    return flattenConfigKeys($config);
}

it('reads only config keys the package actually ships', function (): void {
    $shipped = shippedConfigKeys();
    /** @var array<string, mixed> $config */
    $config = require __DIR__.'/../../config/reports.php';

    $read = [];

    foreach ([...packageSourceFiles(__DIR__.'/../../src'), ...packageSourceFiles(__DIR__.'/../../database/migrations')] as $file) {
        $read = [...$read, ...configKeysReadIn($file)];
    }

    $read = array_values(array_unique($read));

    expect($read)->not->toBeEmpty();

    foreach ($read as $key) {
        expect(Arr::has($config, $key))
            ->toBeTrue("config('reports.{$key}') is read in src/ but config/reports.php never ships it");
    }

    // Guards the guard: the scrape really did see the keys we expect.
    expect($read)->toContain('model', 'table', 'key_type', 'threshold', 'moderation.default_rule')
        ->and($shipped)->toContain('key_type')
        ->and($shipped)->not->toContain('morph_key_type');
});

it('ships no config key the package never reads', function (): void {
    $read = [];

    foreach ([...packageSourceFiles(__DIR__.'/../../src'), ...packageSourceFiles(__DIR__.'/../../database/migrations')] as $file) {
        $read = [...$read, ...configKeysReadIn($file)];
    }

    $read = array_values(array_unique($read));

    foreach (shippedConfigKeys() as $key) {
        // A key the package ships but never reads is a documented feature that
        // silently does nothing (the alerts #34 mirror bug).
        expect($read)->toContain($key);
    }
});

it('resolves the report model only through the Support seam', function (): void {
    foreach (packageSourceFiles(__DIR__.'/../../src') as $file) {
        if (str_contains($file, '/Support/')) {
            continue;
        }

        // Every model read must go through Support\ReportModel, so the swap seam
        // cannot be honoured in some call sites and bypassed in others.
        expect(configKeysReadIn($file))->not->toContain('model');
    }
});
