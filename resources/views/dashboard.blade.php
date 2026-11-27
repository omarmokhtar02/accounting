<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('app.dashboard.welcome', ['name' => Auth::user()->name])" />
        @if ($company)
            <p class="mt-1 text-sm text-muted">{{ $company->localizedName() }} · {{ $company->country_code }} · {{ $company->base_currency }}</p>
        @endif
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        {{-- Quick actions: each tile only shows when the user may do it. --}}
        <section aria-labelledby="quick-actions">
            <h2 id="quick-actions" class="mb-3 text-sm font-semibold uppercase tracking-wider text-muted">{{ __('app.dashboard.quick_actions') }}</h2>

            @php
                $actions = [
                    ['journals.create', 'accounting.journal-entries.create', 'book', 'accounting.new_journal_entry', 'app.dashboard.new_journal_hint'],
                    ['reports.view', 'accounting.reports.trial-balance', 'chart', 'accounting.trial_balance', 'app.dashboard.trial_balance_hint'],
                    ['imports.run', 'accounting.imports.index', 'upload', 'accounting.import.title', 'app.dashboard.import_hint'],
                    ['accounts.view', 'accounting.exchange-rates.index', 'currency', 'accounting.fx_title', 'app.dashboard.rates_hint'],
                    ['accounts.view', 'accounting.accounts.index', 'document', 'accounting.accounts', 'app.dashboard.accounts_hint'],
                    ['periods.view', 'accounting.fiscal-years.index', 'calendar', 'accounting.fiscal_years', 'app.dashboard.years_hint'],
                ];
            @endphp

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($actions as [$permission, $route, $icon, $label, $hint])
                    @can($permission)
                        <a href="{{ route($route) }}" class="group flex items-start gap-4 rounded-xl border border-line bg-card p-5 shadow-sm transition hover:border-primary-200 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600 transition group-hover:bg-primary-600 group-hover:text-white">
                                <x-icon :name="$icon" class="h-6 w-6" />
                            </span>
                            <span>
                                <span class="block font-semibold text-ink">{{ __($label) }}</span>
                                <span class="mt-0.5 block text-sm text-muted">{{ __($hint) }}</span>
                            </span>
                        </a>
                    @endcan
                @endforeach
            </div>
        </section>

        {{-- Every plan the company runs: a company can hold several at once. --}}
        <section aria-labelledby="plans">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="plans" class="text-sm font-semibold uppercase tracking-wider text-muted">{{ __('app.dashboard.your_plans') }}</h2>
                @can('users.manage')
                    <a href="{{ route('billing.show') }}" class="text-sm font-medium text-primary-600 hover:underline">{{ __('app.dashboard.manage_billing') }}</a>
                @endcan
            </div>

            @if ($subscriptions->isEmpty())
                <x-ui.alert type="warning">{{ __('subscriptions.no_subscription') }}</x-ui.alert>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($subscriptions as $subscription)
                        <div class="rounded-xl border border-line bg-card p-5 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-semibold text-ink">{{ $subscription->plan->localizedName() }}</h3>
                                <x-ui.badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-ui.badge>
                            </div>
                            @if ($subscription->accessEndsAt())
                                <p class="mt-2 text-sm text-muted">
                                    {{ $subscription->status->value === 'trialing' ? __('subscriptions.trial_ends') : __('subscriptions.period_ends') }}:
                                    <span class="text-ink">{{ $subscription->accessEndsAt()->toDateString() }}</span>
                                    @if ($subscription->daysLeft())
                                        · {{ __('subscriptions.days_left', ['days' => $subscription->daysLeft()]) }}
                                    @endif
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
