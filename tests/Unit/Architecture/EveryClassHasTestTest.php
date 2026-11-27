<?php

/*
 * Guard: every class under Modules/ and app/ must be named in a `covers(...)` call of some test file.
 *
 * It proves a test EXISTS and is declared against the class; it cannot prove the test is good.
 * Quality is enforced separately by coverage (pest --coverage --min=90 in CI, needs pcov) and by
 * the invariant / tie-out / contract suites.
 *
 * Exempt: Laravel Breeze scaffolding (auth controllers, profile, auth requests, layout components),
 * which ships with and is covered by Breeze's own feature tests (tests/Feature/Auth, ProfileTest).
 * Also exempt (nothing to test on their own): interfaces, abstract classes, enums with no methods,
 * and files that declare no class (routes, views).
 *
 * tests/.untested-baseline.json lists classes still waiting for a test. It may only SHRINK:
 * adding a new class without a test fails this guard, and a baseline entry that now has a test
 * must be removed.
 */

/** Breeze scaffolding, tested by Breeze's own feature tests. */
const BREEZE_SCAFFOLDING = [
    'app/Http/Controllers/Auth/', 'app/Http/Controllers/ProfileController.php', 'app/Http/Controllers/Controller.php',
    'app/Http/Requests/', 'app/View/Components/AppLayout.php', 'app/View/Components/GuestLayout.php',
];

function projectRoot(): string
{
    return str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__, 3));
}

/** @return list<string> relative paths (forward slashes) of every PHP file under Modules/ and app/ */
function sourceFiles(): array
{
    $root = projectRoot();
    $files = [];

    foreach (['Modules', 'app'] as $dir) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $relative = ltrim(str_replace($root, '', str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname())), '/');

            if ($file->getExtension() === 'php' && ! str_contains($relative, 'Views')) {
                $files[] = $relative;
            }
        }
    }

    return $files;
}

function isBreezeScaffolding(string $relative): bool
{
    foreach (BREEZE_SCAFFOLDING as $prefix) {
        if (str_starts_with($relative, $prefix)) {
            return true;
        }
    }

    return false;
}

/** @return array<string, string> fully qualified class name => relative file path */
function moduleClasses(): array
{
    $root = projectRoot();
    $classes = [];

    foreach (sourceFiles() as $relative) {
        if (isBreezeScaffolding($relative)) {
            continue;
        }

        $source = file_get_contents($root.'/'.$relative);

        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)
            || ! preg_match('/^(abstract\s+|final\s+|readonly\s+)*(class|trait|enum|interface)\s+(\w+)/m', $source, $decl)) {
            continue;
        }

        $kind = $decl[2];

        if ($kind === 'interface' || str_contains($decl[0], 'abstract')) {
            continue;
        }

        if ($kind === 'enum' && ! str_contains($source, 'function ')) {
            continue;
        }

        $classes[$ns[1].'\\'.$decl[3]] = $relative;
    }

    ksort($classes);

    return $classes;
}

/** @return array<string, true> fully qualified class names named in covers() calls */
function coveredClasses(): array
{
    $covered = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2), FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (! preg_match_all('/\bcovers\(([^;]*)\);/s', $source, $calls)) {
            continue;
        }

        preg_match_all('/^use\s+([^;\s]+?)(?:\s+as\s+(\w+))?;/m', $source, $uses, PREG_SET_ORDER);
        $imports = [];

        foreach ($uses as $use) {
            $imports[$use[2] ?? substr(strrchr('\\'.$use[1], '\\'), 1)] = $use[1];
        }

        foreach ($calls[1] as $arguments) {
            preg_match_all('/([\w\\\\]+)::class/', $arguments, $names);

            foreach ($names[1] as $short) {
                $covered[$imports[$short] ?? ltrim($short, '\\')] = true;
            }
        }
    }

    return $covered;
}

/** @return list<string> */
function untestedBaseline(): array
{
    $file = dirname(__DIR__).'/.untested-baseline.json';

    return is_file($file) ? json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : [];
}

it('has a covers() test for every class, except the shrinking baseline', function () {
    $untested = array_values(array_diff(array_keys(moduleClasses()), array_keys(coveredClasses())));
    $unexpected = array_values(array_diff($untested, untestedBaseline()));

    expect($unexpected)->toBe([], "Classes without a test (write the test, do not extend the baseline):\n".implode("\n", $unexpected));
});

it('keeps the baseline honest: it lists only classes that are still untested', function () {
    $untested = array_diff(array_keys(moduleClasses()), array_keys(coveredClasses()));
    $stale = array_values(array_diff(untestedBaseline(), $untested));

    expect($stale)->toBe([], "Remove from tests/.untested-baseline.json (now tested or gone):\n".implode("\n", $stale));
});

it('scans a meaningful number of classes so the guard cannot silently pass on an empty list', function () {
    expect(count(moduleClasses()))->toBeGreaterThan(60);
});
