<?php

/*
 * The product is bilingual. Every translation key that exists in English must exist in Arabic and
 * vice versa, with the same placeholders, so no screen ever shows a raw key in one language.
 */

/** Our own translation files (framework files such as validation.php are intentionally partial). */
const OWN_LANG_FILES = ['accounting', 'admin', 'app', 'platform', 'subscriptions', 'tax'];

function flattenKeys(array $array, string $prefix = ''): array
{
    $keys = [];

    foreach ($array as $key => $value) {
        is_array($value)
            ? $keys += flattenKeys($value, $prefix.$key.'.')
            : $keys[$prefix.$key] = $value;
    }

    return $keys;
}

function placeholders(string $text): array
{
    preg_match_all('/:(\w+)/', $text, $matches);
    sort($matches[1]);

    return array_values(array_unique($matches[1]));
}

it('has the same keys in English and Arabic', function (string $file) {
    $en = flattenKeys(require lang_path("en/{$file}.php"));
    $ar = flattenKeys(require lang_path("ar/{$file}.php"));

    expect(array_values(array_diff(array_keys($en), array_keys($ar))))->toBe([], "Missing in Arabic ({$file}.php)")
        ->and(array_values(array_diff(array_keys($ar), array_keys($en))))->toBe([], "Missing in English ({$file}.php)");
})->with(OWN_LANG_FILES);

it('uses the same placeholders in both languages', function (string $file) {
    $en = flattenKeys(require lang_path("en/{$file}.php"));
    $ar = flattenKeys(require lang_path("ar/{$file}.php"));

    foreach ($en as $key => $text) {
        expect(placeholders((string) ($ar[$key] ?? '')))->toBe(placeholders((string) $text), "Placeholders differ for {$file}.{$key}");
    }
})->with(OWN_LANG_FILES);

it('has no empty translations', function (string $file) {
    foreach (['en', 'ar'] as $locale) {
        foreach (flattenKeys(require lang_path("{$locale}/{$file}.php")) as $key => $text) {
            expect(trim((string) $text))->not->toBeEmpty("{$locale}/{$file}.{$key} is empty");
        }
    }
})->with(OWN_LANG_FILES);

it('writes the Arabic files in Arabic script', function () {
    foreach (['accounting', 'admin'] as $file) {
        $text = implode(' ', flattenKeys(require lang_path("ar/{$file}.php")));

        expect(preg_match_all('/\p{Arabic}/u', $text))->toBeGreaterThan(100);
    }
});

it('has an Arabic translation for every Breeze string used in the layouts', function () {
    $json = json_decode(file_get_contents(lang_path('ar.json')), true, flags: JSON_THROW_ON_ERROR);

    foreach (['Dashboard', 'Profile', 'Log Out', 'Log in', 'Register', 'Email', 'Password'] as $key) {
        expect($json)->toHaveKey($key);
    }
});
