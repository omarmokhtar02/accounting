<?php

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

covers(AppServiceProvider::class);

afterEach(function () {
    // Undo anything the production-mode boot changed so other tests are unaffected.
    app()['env'] = 'testing';
    URL::forceScheme(null);
    DB::prohibitDestructiveCommands(false);
    Model::preventAccessingMissingAttributes(true);
    Model::preventSilentlyDiscardingAttributes(true);
    Password::defaults(fn () => Password::min(8));
});

function bootAs(string $environment): void
{
    app()['env'] = $environment;
    (new AppServiceProvider(app()))->boot();
}

it('catches missing and silently discarded attributes outside production', function () {
    bootAs('local');

    expect(Model::preventsAccessingMissingAttributes())->toBeTrue()->and(Model::preventsSilentlyDiscardingAttributes())->toBeTrue();
});

it('relaxes strict models in production so a stray attribute cannot take the site down', function () {
    bootAs('production');

    expect(Model::preventsAccessingMissingAttributes())->toBeFalse()->and(Model::preventsSilentlyDiscardingAttributes())->toBeFalse();
});

it('forces https URLs in production only', function () {
    bootAs('local');
    expect(URL::to('/x'))->toStartWith('http://');

    bootAs('production');
    expect(URL::to('/x'))->toStartWith('https://');
});

it('prohibits destructive database commands in production', function () {
    bootAs('production');

    $exit = Artisan::call('migrate:fresh');

    expect($exit)->toBe(1)->and(Artisan::output())->toContain('prohibited');
});

it('demands strong passwords in production and sensible ones elsewhere', function () {
    bootAs('production');
    $strict = Validator::make(['p' => 'alllowercase1'], ['p' => Password::default()]);
    expect($strict->fails())->toBeTrue();

    bootAs('local');
    $relaxed = Validator::make(['p' => 'longenough'], ['p' => Password::default()]);
    expect($relaxed->fails())->toBeFalse();
});

it('registers no extra bindings of its own', function () {
    expect((new AppServiceProvider(app()))->register())->toBeNull();
});
