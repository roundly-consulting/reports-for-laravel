<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RoundlyConsulting\Reports\ReportsServiceProvider;

/**
 * @return list<string>
 */
function migrationSources(): array
{
    $files = array_map(
        static fn (string $file): string => (string) realpath($file),
        (array) glob(__DIR__.'/../../database/migrations/*.php'),
    );

    sort($files);

    return array_values($files);
}

/**
 * The `<table> => <migration file>` map of every table CREATEd by the package,
 * covering the literal (`Schema::create('x', …)`) and variable
 * (`Schema::create($tableName, …)`, config-driven) forms alike.
 *
 * @return array<string, string>
 */
function createdTables(): array
{
    $created = [];

    foreach (migrationSources() as $file) {
        $source = (string) file_get_contents($file);

        preg_match_all('/Schema::create\(\s*\'(\w+)\'/', $source, $matches);

        foreach ($matches[1] as $table) {
            $created[$table] = $file;
        }

        // A config-driven table name: the CREATE lives in this file even though
        // the literal is in the config, so pin it by the migration's own name.
        if (preg_match('/Schema::create\(\s*\$(\w+)/', $source) === 1) {
            preg_match('/create_(\w+)_table/', basename($file), $named);
            $created[$named[1] ?? basename($file)] = $file;
        }
    }

    return $created;
}

it('never auto-loads its migrations', function (): void {
    $paths = array_map(
        static fn (string $path): string => (string) realpath($path),
        app('migrator')->paths(),
    );

    // Migrations are publish-only: the provider must never register its own
    // directory with the migrator.
    expect($paths)->not->toContain((string) realpath(__DIR__.'/../../database/migrations'));
});

it('publishes every migration source under the reports-migrations tag', function (): void {
    $published = ServiceProvider::pathsToPublish(ReportsServiceProvider::class, 'reports-migrations');

    expect(array_keys($published))->toBe(migrationSources());

    foreach ($published as $source => $destination) {
        expect($destination)
            ->toStartWith(database_path('migrations'))
            ->toMatch('/\/\d{4}_\d{2}_\d{2}_\d{6}_'.preg_quote(basename((string) $source), '/').'$/');
    }
});

it('keeps every foreign key behind the migration that creates its parent', function (): void {
    $sources = migrationSources();
    $created = createdTables();
    $order = array_flip($sources);

    $edges = 0;

    foreach ($sources as $file) {
        $source = (string) file_get_contents($file);

        $parents = [];

        // ->constrained('parent')
        preg_match_all('/->constrained\(\s*\'(\w+)\'/', $source, $explicit);
        $parents = [...$parents, ...$explicit[1]];

        // ->references('id')->on('parent')
        preg_match_all('/->on\(\s*\'(\w+)\'/', $source, $longhand);
        $parents = [...$parents, ...$longhand[1]];

        // bare ->constrained() on foreignId('parent_id') — parent derived from the column
        preg_match_all('/foreignId\(\s*\'(\w+)_id\'\s*\)->constrained\(\s*\)/', $source, $bare);
        foreach ($bare[1] as $singular) {
            $parents[] = Str::plural($singular);
        }

        foreach ($parents as $parent) {
            $edges++;

            // The parent's CREATE must exist, and must sort no later than the
            // migration constraining onto it (a self-reference sorts with its
            // own file).
            expect($created)->toHaveKey($parent);
            expect($order[$created[$parent]])->toBeLessThanOrEqual($order[$file]);
        }

        // An ALTER must follow the CREATE of the table it touches.
        preg_match_all('/Schema::table\(\s*\'(\w+)\'/', $source, $altered);

        foreach ($altered[1] as $table) {
            expect($created)->toHaveKey($table);
            expect($order[$created[$table]])->toBeLessThan($order[$file]);
        }
    }

    // Guards the guard: reports ships exactly one migration and zero FK edges —
    // if either changes, this pin must be revisited, not silently satisfied.
    expect($sources)->toHaveCount(1)
        ->and($created)->toHaveKey('reports')
        ->and($edges)->toBe(0);
});

it('migrates the published filenames into a fresh empty database', function (): void {
    $directory = sys_get_temp_dir().'/reports-publish-'.uniqid();
    File::makeDirectory($directory, recursive: true);

    $published = ServiceProvider::pathsToPublish(ReportsServiceProvider::class, 'reports-migrations');

    foreach ($published as $source => $destination) {
        File::copy((string) $source, $directory.'/'.basename((string) $destination));
    }

    Schema::dropIfExists('reports');

    $this->artisan('migrate', ['--path' => $directory, '--realpath' => true])->assertSuccessful();

    expect(Schema::hasTable('reports'))->toBeTrue()
        ->and(Schema::hasColumns('reports', ['reporter_id', 'reported_id', 'resolved_by_id', 'status']))->toBeTrue();

    File::deleteDirectory($directory);
});
