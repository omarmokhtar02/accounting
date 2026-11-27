@props(['theme' => 'app', 'brand' => null, 'home' => '/', 'title' => null])

{{--
    Dashboard shell shared by the company app and the platform admin: fixed sidebar (a drawer on phones),
    sticky top bar, optional page header. Slots: nav (sidebar links), topbar (right side of the top bar),
    banner (notice under the top bar), header (page title), default (content).
    Uses logical classes (start/end/ps) so it mirrors itself for Arabic without a second stylesheet.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="{{ $theme }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @if ($theme === 'admin')
            <meta name="robots" content="noindex, nofollow">
        @endif

        <title>{{ $title ? $title.' · ' : '' }}{{ $brand ?? config('app.name') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|cairo:400,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased bg-surface {{ app()->getLocale() === 'ar' ? 'font-arabic' : '' }}">
        <div x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false" class="min-h-screen">
            {{-- Backdrop behind the drawer on small screens. --}}
            <div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-ink/50 lg:hidden" style="display: none;"></div>

            <aside id="sidebar"
                class="fixed inset-y-0 start-0 z-50 flex w-64 flex-col bg-sidebar text-sidebar-text transition-transform duration-200"
                :class="sidebarOpen ? 'translate-x-0' : 'max-lg:ltr:-translate-x-full max-lg:rtl:translate-x-full'"
                aria-label="{{ __('app.navigation') }}">
                <div class="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-line px-5">
                    <a href="{{ $home }}" class="flex min-w-0 items-center gap-3 text-white">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-primary-600">
                            <x-application-logo class="h-5 w-5" />
                        </span>
                        <span class="truncate text-base font-semibold">{{ $brand ?? config('app.name') }}</span>
                    </a>
                    <button type="button" @click="sidebarOpen = false" class="ms-auto rounded-md p-1 text-sidebar-muted hover:text-white lg:hidden" aria-label="{{ __('app.close') }}">
                        <x-icon name="close" />
                    </button>
                </div>

                <nav class="sidebar-scroll flex-1 overflow-y-auto px-3 pb-6">
                    {{ $nav }}
                </nav>

                @isset($sidebarFooter)
                    <div class="shrink-0 border-t border-sidebar-line p-3">{{ $sidebarFooter }}</div>
                @endisset
            </aside>

            <div class="lg:ps-64">
                <div class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-card/90 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button type="button" @click="sidebarOpen = true" class="rounded-md p-2 text-muted hover:bg-surface hover:text-ink lg:hidden" aria-label="{{ __('app.open_menu') }}" aria-controls="sidebar">
                        <x-icon name="menu" />
                    </button>
                    <div class="ms-auto flex items-center gap-3">
                        @isset($topbar){{ $topbar }}@endisset
                    </div>
                </div>

                @isset($banner){{ $banner }}@endisset

                @isset($header)
                    <header class="border-b border-line bg-card">
                        <div class="px-4 py-5 sm:px-6 lg:px-8">{{ $header }}</div>
                    </header>
                @endisset

                <main>
                    @if (session('status'))
                        <div class="px-4 pt-4 sm:px-6 lg:px-8"><x-ui.alert>{{ session('status') }}</x-ui.alert></div>
                    @endif
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
