<x-shell theme="admin" :brand="__('platform.platform')" :home="route('platform.dashboard')">
    <x-slot name="nav">
        <x-nav.group>
            @adminCan('dashboard.view')
                <x-nav.item :href="route('platform.dashboard')" icon="home" :active="request()->routeIs('platform.dashboard')">{{ __('platform.nav.dashboard') }}</x-nav.item>
            @endadminCan
        </x-nav.group>

        <x-nav.group :label="__('platform.nav_group.customers')">
            @adminCan('tenants.view')
                <x-nav.item :href="route('platform.tenants.index')" icon="building" :active="request()->routeIs('platform.tenants.*', 'platform.subscriptions.*')">{{ __('platform.nav.tenants') }}</x-nav.item>
            @endadminCan
            @adminCan('plans.manage')
                <x-nav.item :href="route('platform.plans.index')" icon="layers" :active="request()->routeIs('platform.plans.*')">{{ __('platform.nav.plans') }}</x-nav.item>
            @endadminCan
        </x-nav.group>

        <x-nav.group :label="__('platform.nav_group.configuration')">
            @adminCan('settings.manage')
                <x-nav.item :href="route('platform.settings.edit')" icon="sliders" :active="request()->routeIs('platform.settings.*')">{{ __('platform.nav.settings') }}</x-nav.item>
            @endadminCan
            @adminCan('tax.manage')
                <x-nav.item :href="route('platform.tax-rates.index')" icon="percent" :active="request()->routeIs('platform.tax-rates.*')">{{ __('platform.nav.tax_rates') }}</x-nav.item>
            @endadminCan
        </x-nav.group>

        <x-nav.group :label="__('platform.nav_group.team')">
            @adminCan('admins.manage')
                <x-nav.item :href="route('platform.admins.index')" icon="users" :active="request()->routeIs('platform.admins.*')">{{ __('platform.nav.admins') }}</x-nav.item>
            @endadminCan
            @adminCan('roles.manage')
                <x-nav.item :href="route('platform.roles.index')" icon="key" :active="request()->routeIs('platform.roles.*')">{{ __('platform.nav.roles') }}</x-nav.item>
            @endadminCan
            @adminCan('audit.view')
                <x-nav.item :href="route('platform.audit.index')" icon="clock" :active="request()->routeIs('platform.audit.*')">{{ __('platform.nav.audit') }}</x-nav.item>
            @endadminCan
        </x-nav.group>
    </x-slot>

    <x-slot name="topbar">
        <a href="{{ route('platform.locale', app()->getLocale() === 'ar' ? 'en' : 'ar') }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-muted hover:bg-surface hover:text-ink">
            <x-icon name="globe" class="h-4 w-4" />
            {{ __('platform.language') }}
        </a>

        <span class="flex items-center gap-2 text-sm">
            <span class="grid h-8 w-8 place-items-center rounded-full bg-primary-100 font-semibold text-primary-700">{{ mb_strtoupper(mb_substr((string) auth('admin')->user()?->name, 0, 1)) }}</span>
            <span class="hidden font-medium text-ink sm:block">{{ auth('admin')->user()?->name }}</span>
        </span>

        <form method="POST" action="{{ route('platform.logout') }}">
            @csrf
            <button class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-muted hover:bg-surface hover:text-ink">
                <x-icon name="logout" class="h-4 w-4" />
                <span class="hidden sm:inline">{{ __('platform.logout') }}</span>
            </button>
        </form>
    </x-slot>

    @isset($header)
        <x-slot name="header">{{ $header }}</x-slot>
    @endisset

    <div class="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        @if ($errors->any())
            <x-ui.alert type="danger">
                <ul class="ms-5 list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </x-ui.alert>
        @endif
        {{ $slot }}
    </div>
</x-shell>
