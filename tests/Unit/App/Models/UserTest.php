<?php

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

covers(User::class);

beforeEach(function () {
    ['user' => $this->user, 'company' => $this->company] = bootstrapTenant();
});

it('belongs to a tenant and many companies', function () {
    expect($this->user->tenant->id)->toBe($this->user->tenant_id)
        ->and($this->user->companies->pluck('id'))->toContain($this->company->id);
});

it('hashes the password and hides secrets when serialised', function () {
    expect($this->user->password)->not->toBe('password')->and(Hash::check('password', $this->user->password))->toBeTrue()
        ->and($this->user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('only mass-assigns name, email, password and locale', function () {
    expect((new User)->getFillable())->toBe(['name', 'email', 'password', 'locale']);
});

it('casts the verification time', function () {
    $this->user->forceFill(['email_verified_at' => now()])->save();

    expect($this->user->fresh()->email_verified_at)->toBeInstanceOf(Carbon\Carbon::class);
});

it('has role based permissions scoped to its tenant (spatie teams)', function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->user->tenant_id);

    expect($this->user->hasRole('owner'))->toBeTrue()->and($this->user->can('journals.post'))->toBeTrue();

    ['user' => $other] = bootstrapTenant('other@example.com', 'Other');
    app(PermissionRegistrar::class)->setPermissionsTeamId($other->tenant_id);

    // A role of another tenant never applies in this tenant's team.
    expect($this->user->fresh()->hasRole('owner'))->toBeFalse();
});

it('defaults to English until the user chooses', function () {
    expect($this->user->locale)->toBe('en');
});
