<?php

it('sends visitors who are signed out to the login form', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('sends signed-in users to the dashboard', function () {
    bootstrapTenant();

    $this->get('/')->assertRedirect(route('dashboard'));
});

it('shows the branded login page in English and Arabic', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('Welcome back')->assertSee('double-entry')->assertSee('dir="ltr"', false)
        ->assertSee('Create an account');

    $this->withSession(['locale' => 'ar'])->get(route('login'))->assertOk()
        ->assertSee('أهلاً بعودتك')->assertSee('dir="rtl"', false);
});

it('shows the registration page with the country list', function () {
    $this->get(route('register'))->assertOk()->assertSee('Create your account')->assertSee('Egypt');
});
