<x-shell theme="app" :home="route('dashboard')">
    <x-slot name="nav">
        @include('layouts.navigation')
    </x-slot>

    <x-slot name="topbar">
        @include('layouts.topbar')
    </x-slot>

    @if ($subscriptionBanner)
        <x-slot name="banner">
            <div @class([
                'border-b text-sm',
                'bg-info-50 text-info-800 border-info-600/20' => $subscriptionBanner['type'] === 'trial',
                'bg-warning-50 text-warning-800 border-warning-600/20' => $subscriptionBanner['type'] === 'read_only',
                'bg-danger-50 text-danger-700 border-danger-600/20' => $subscriptionBanner['type'] === 'blocked',
            ])>
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 sm:px-6 lg:px-8">
                    <span class="flex items-center gap-2">
                        <x-icon name="bolt" class="h-4 w-4 shrink-0" />
                        @if ($subscriptionBanner['type'] === 'trial')
                            {{ __('subscriptions.banner_trial', ['days' => $subscriptionBanner['days']]) }}
                        @else
                            {{ __('subscriptions.banner_'.$subscriptionBanner['type']) }}
                        @endif
                    </span>
                    <a href="{{ route('billing.show') }}" class="font-semibold underline underline-offset-2">{{ __('subscriptions.banner_trial_cta') }}</a>
                </div>
            </div>
        </x-slot>
    @endif

    @isset($header)
        <x-slot name="header">{{ $header }}</x-slot>
    @endisset

    {{ $slot }}
</x-shell>
