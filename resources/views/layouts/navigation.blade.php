{{-- Sidebar links of the company app. Every link is behind its permission; empty groups disappear. --}}
<x-nav.group>
    <x-nav.item :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">{{ __('Dashboard') }}</x-nav.item>
</x-nav.group>

<x-nav.group :label="__('app.nav.accounting')">
    @can('journals.view')
        <x-nav.item :href="route('accounting.journal-entries.index')" icon="book" :active="request()->routeIs('accounting.journal-entries.*')">{{ __('accounting.journal_entries') }}</x-nav.item>
    @endcan
    @can('accounts.view')
        <x-nav.item :href="route('accounting.accounts.index')" icon="document" :active="request()->routeIs('accounting.accounts.*', 'accounting.account-roles.*')">{{ __('accounting.accounts') }}</x-nav.item>
        <x-nav.item :href="route('accounting.exchange-rates.index')" icon="currency" :active="request()->routeIs('accounting.exchange-rates.*')">{{ __('accounting.fx_title') }}</x-nav.item>
    @endcan
    @can('periods.view')
        <x-nav.item :href="route('accounting.fiscal-years.index')" icon="calendar" :active="request()->routeIs('accounting.fiscal-years.*')">{{ __('accounting.fiscal_years') }}</x-nav.item>
    @endcan
    @can('tax.view')
        <x-nav.item :href="route('tax.codes.index')" icon="percent" :active="request()->routeIs('tax.*')">{{ __('tax.codes_title') }}</x-nav.item>
    @endcan
</x-nav.group>

<x-nav.group :label="__('app.nav.reports')">
    @can('reports.view')
        <x-nav.item :href="route('accounting.reports.trial-balance')" icon="chart" :active="request()->routeIs('accounting.reports.trial-balance')">{{ __('accounting.trial_balance') }}</x-nav.item>
        <x-nav.item :href="route('accounting.reports.general-ledger')" icon="document" :active="request()->routeIs('accounting.reports.general-ledger')">{{ __('accounting.general_ledger') }}</x-nav.item>
    @endcan
</x-nav.group>

<x-nav.group :label="__('app.nav.data')">
    @can('imports.run')
        <x-nav.item :href="route('accounting.imports.index')" icon="upload" :active="request()->routeIs('accounting.imports.*')">{{ __('accounting.import.title') }}</x-nav.item>
    @endcan
</x-nav.group>

<x-nav.group :label="__('app.nav.organization')">
    @can('companies.manage')
        <x-nav.item :href="route('localization.companies.index')" icon="building" :active="request()->routeIs('localization.companies.*')">{{ __('app.companies') }}</x-nav.item>
    @endcan
    @can('users.manage')
        <x-nav.item :href="route('admin.users.index')" icon="users" :active="request()->routeIs('admin.users.*')">{{ __('admin.users') }}</x-nav.item>
        <x-nav.item :href="route('billing.show')" icon="card" :active="request()->routeIs('billing.*')">{{ __('subscriptions.billing') }}</x-nav.item>
    @endcan
    @can('roles.manage')
        <x-nav.item :href="route('admin.roles.index')" icon="key" :active="request()->routeIs('admin.roles.*')">{{ __('admin.roles') }}</x-nav.item>
    @endcan
</x-nav.group>
