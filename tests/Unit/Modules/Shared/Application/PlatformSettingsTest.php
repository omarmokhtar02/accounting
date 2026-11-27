<?php

use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\PlatformSettings;

covers(PlatformSettings::class);

beforeEach(function () {
    $this->settings = new PlatformSettings;
});

it('returns the declared default until an admin overrides it', function () {
    expect($this->settings->int('trial.days'))->toBe(14)
        ->and($this->settings->int('subscription.readonly_grace_days'))->toBe(7)
        ->and($this->settings->string('trial.plan_code'))->toBe('business')
        ->and($this->settings->get('trial.days'))->toBeInt();
});

it('stores an override and reads it back, typed', function () {
    $this->settings->set('trial.days', '30');

    expect($this->settings->int('trial.days'))->toBe(30)->and((new PlatformSettings)->int('trial.days'))->toBe(30);
});

it('updates an existing value instead of duplicating it', function () {
    $this->settings->set('trial.days', 21);
    $this->settings->set('trial.days', 3);

    expect(DB::table('platform_settings')->where('key', 'trial.days')->count())->toBe(1)->and($this->settings->int('trial.days'))->toBe(3);
});

it('accepts zero (no trial) but rejects negative or non numeric integers', function (mixed $value) {
    $this->settings->set('trial.days', $value);
})->with([-1, 'abc', ''])->throws(InvalidArgumentException::class);

it('allows a zero length trial', function () {
    $this->settings->set('trial.days', 0);

    expect($this->settings->int('trial.days'))->toBe(0);
});

it('stores text settings', function () {
    $this->settings->set('billing.contact_email', 'sales@example.com');

    expect($this->settings->string('billing.contact_email'))->toBe('sales@example.com');
});

it('refuses unknown keys on read and write', function () {
    expect(fn () => $this->settings->get('nope'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->settings->set('nope', 1))->toThrow(InvalidArgumentException::class);
});

it('lists every setting with its effective value', function () {
    $this->settings->set('trial.days', 10);
    $effective = $this->settings->effective();

    expect(array_keys($effective))->toBe(array_keys(PlatformSettings::DEFINITIONS))->and($effective['trial.days'])->toBe(10)
        ->and($effective['subscription.readonly_grace_days'])->toBe(7);
});

it('caches reads until forgotten', function () {
    $this->settings->int('trial.days');
    DB::table('platform_settings')->insert(['key' => 'trial.days', 'value' => '99', 'type' => 'int', 'created_at' => now(), 'updated_at' => now()]);

    expect($this->settings->int('trial.days'))->toBe(14);

    $this->settings->forget();

    expect($this->settings->int('trial.days'))->toBe(99);
});
