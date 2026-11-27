<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>{{ __('platform.login') }}</title>
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|cairo:400,600&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface {{ app()->getLocale() === 'ar' ? 'font-arabic' : '' }}">
        <div class="min-h-screen flex flex-col items-center justify-center px-4">
            <div class="w-full max-w-md bg-card shadow-md rounded-lg p-6">
                <h1 class="text-lg font-semibold text-ink mb-4">{{ __('platform.login') }}</h1>

                <form method="POST" action="{{ route('platform.login.store') }}" class="space-y-4">
                    @csrf
                    <x-form.field name="email" type="email" :label="__('platform.email')" required autofocus />
                    <x-form.field name="password" type="password" :label="__('platform.password')" required />
                    <x-form.checkbox name="remember" :label="__('platform.remember')" />
                    <x-ui.button class="w-full">{{ __('platform.sign_in') }}</x-ui.button>
                </form>

                <p class="mt-4 text-center text-sm text-muted">
                    <a class="hover:underline" href="{{ route('platform.locale', app()->getLocale() === 'ar' ? 'en' : 'ar') }}">{{ __('platform.language') }}</a>
                </p>
            </div>
        </div>
    </body>
</html>
