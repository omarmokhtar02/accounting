<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name', 'Accounting') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|cairo:400,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased {{ app()->getLocale() === 'ar' ? 'font-arabic' : '' }}">
        <div class="min-h-screen grid lg:grid-cols-2">
            {{-- Brand panel: hidden on small screens, where the logo is shown above the form instead. --}}
            <aside class="hidden lg:flex flex-col justify-between bg-gradient-to-br from-primary-700 to-primary-500 text-white p-12">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <x-application-logo class="w-10 h-10" />
                    <span class="text-xl font-semibold">{{ config('app.name') }}</span>
                </a>

                <div class="max-w-md space-y-8">
                    <h2 class="text-3xl font-semibold leading-snug">{{ __('app.auth.tagline') }}</h2>
                    <ul class="space-y-4 text-primary-100">
                        @foreach (['feature_ledger', 'feature_countries', 'feature_bilingual'] as $feature)
                            <li class="flex items-start gap-3">
                                <svg class="w-5 h-5 mt-0.5 shrink-0 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                <span>{{ __('app.auth.'.$feature) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="text-sm text-primary-100">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
            </aside>

            <main class="flex flex-col items-center justify-center bg-surface px-6 py-10">
                <div class="w-full max-w-md">
                    <a href="{{ url('/') }}" class="flex lg:hidden items-center justify-center gap-2 mb-8 text-primary-600">
                        <x-application-logo class="w-9 h-9" />
                        <span class="text-lg font-semibold text-ink">{{ config('app.name') }}</span>
                    </a>

                    @isset($title)
                        <h1 class="text-2xl font-semibold text-ink">{{ $title }}</h1>
                    @endisset
                    @isset($subtitle)
                        <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                    @endisset

                    <div class="mt-6 bg-card shadow-sm border border-line rounded-xl p-6 sm:p-8">
                        {{ $slot }}
                    </div>

                    @isset($footer)
                        <p class="mt-6 text-center text-sm text-muted">{{ $footer }}</p>
                    @endisset

                    <p class="mt-6 text-center text-sm text-muted">
                        <a href="{{ route('locale.update', 'en') }}" @class(['hover:underline', 'font-semibold text-ink' => app()->getLocale() === 'en'])>English</a>
                        <span aria-hidden="true">·</span>
                        <a href="{{ route('locale.update', 'ar') }}" @class(['hover:underline', 'font-semibold text-ink' => app()->getLocale() === 'ar'])>العربية</a>
                    </p>
                </div>
            </main>
        </div>
    </body>
</html>
