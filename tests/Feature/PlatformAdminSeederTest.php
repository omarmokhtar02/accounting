<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminUser;

uses(RefreshDatabase::class);

function configureAdmin(?string $email, ?string $password, string $name = 'Boss'): void
{
    config(['platform.admin.email' => $email, 'platform.admin.password' => $password, 'platform.admin.name' => $name]);
}

it('creates the first super admin from the configuration', function () {
    configureAdmin('Boss@Example.com', 'a-long-secret-123');

    $this->seed(PlatformAdminSeeder::class);

    $admin = AdminUser::where('email', 'boss@example.com')->first();

    expect($admin)->not->toBeNull()->and($admin->isSuperAdmin())->toBeTrue()->and($admin->is_active)->toBeTrue()
        ->and($admin->name)->toBe('Boss')->and($admin->password)->not->toBe('a-long-secret-123');

    $this->post(route('platform.login.store'), ['email' => 'boss@example.com', 'password' => 'a-long-secret-123'])
        ->assertRedirect();
    $this->assertAuthenticated('admin');
});

it('does nothing without an email and password', function (?string $email, ?string $password) {
    configureAdmin($email, $password);

    $this->seed(PlatformAdminSeeder::class);

    expect(AdminUser::count())->toBe(0);
})->with([[null, null], ['boss@example.com', null], [null, 'a-long-secret-123'], ['', '']]);

it('falls back to the seeder credentials in the local environment', function () {
    configureAdmin(null, null);
    app()->detectEnvironment(fn () => 'local');

    $this->seed(PlatformAdminSeeder::class);
    app()->detectEnvironment(fn () => 'testing');

    $this->post(route('platform.login.store'), ['email' => PlatformAdminSeeder::LOCAL_EMAIL, 'password' => PlatformAdminSeeder::LOCAL_PASSWORD])
        ->assertRedirect();
    $this->assertAuthenticated('admin');
});

it('can run again without changing the existing admin', function () {
    configureAdmin('boss@example.com', 'a-long-secret-123');
    $this->seed(PlatformAdminSeeder::class);
    $hash = AdminUser::first()->password;

    configureAdmin('boss@example.com', 'a-different-secret-456');
    $this->seed(PlatformAdminSeeder::class);

    expect(AdminUser::count())->toBe(1)->and(AdminUser::first()->password)->toBe($hash);
});

it('refuses a weak password instead of creating a weak admin', function () {
    configureAdmin('boss@example.com', 'short');

    $this->seed(PlatformAdminSeeder::class);
})->throws(AdminOperationDenied::class);

it('runs as part of the main seeder', function () {
    configureAdmin('boss@example.com', 'a-long-secret-123');

    $this->seed(DatabaseSeeder::class);

    expect(AdminUser::where('email', 'boss@example.com')->exists())->toBeTrue();
});
