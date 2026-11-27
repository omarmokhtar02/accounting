<x-platform::layout>
    <x-slot name="header">
        <x-ui.page-header :title="$tenant->name">
            <x-slot name="actions">
                @if ($tenant->status === 'active')<x-ui.badge tone="success">{{ __('platform.tenant_active') }}</x-ui.badge>
                @else<x-ui.badge tone="danger">{{ __('platform.tenant_suspended_label') }}</x-ui.badge>@endif
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    @php
        $planOptions = $plans->mapWithKeys(fn ($p) => [$p->id => $p->localizedName()])->all();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('platform.access_level')" :value="__('platform.access.'.$level->value)" icon="shield" :tone="$level->value === 'full' ? 'success' : ($level->value === 'read_only' ? 'warning' : 'danger')" />
        <x-ui.stat :label="__('platform.plans_count')" :value="$subscriptions->count()" icon="layers" />
        <x-ui.stat :label="__('platform.companies')" :value="$companies->count()" icon="building" tone="info" />
        <x-ui.stat :label="__('platform.users')" :value="$users->count()" icon="users" tone="neutral" />
    </div>

    {{-- One card per plan the company holds: it can run several at once. --}}
    <section class="space-y-4" aria-labelledby="plans-heading">
        <h2 id="plans-heading" class="text-base font-semibold text-ink">{{ __('platform.subscription') }}</h2>

        @forelse ($subscriptions as $sub)
            <x-ui.card x-data="{ manage: false }" data-subscription="{{ $sub->id }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-ink">{{ $sub->plan->localizedName() }}</h3>
                        <p class="mt-1 text-sm text-muted">
                            {{ __('platform.ends') }}: <span class="text-ink">{{ $sub->accessEndsAt()?->toDateString() ?? '—' }}</span>
                            @if ($sub->daysLeft()) · {{ __('subscriptions.days_left', ['days' => $sub->daysLeft()]) }} @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.badge :tone="$sub->status->tone()">{{ $sub->status->label() }}</x-ui.badge>
                        @adminCan('subscriptions.manage')
                            <x-ui.button type="button" variant="secondary" size="sm" @click="manage = ! manage">{{ __('platform.manage') }}</x-ui.button>
                        @endadminCan
                    </div>
                </div>

                @adminCan('subscriptions.manage')
                    <div x-show="manage" x-cloak style="display: none;" class="mt-5 grid grid-cols-1 gap-4 border-t border-line pt-5 lg:grid-cols-2">
                        @if ($sub->status->value === 'trialing')
                            <x-form :action="route('platform.subscriptions.extend-trial', $tenant)" :title="__('platform.extend_trial')" :submit="__('platform.extend_trial')" class="shadow-none border border-line">
                                <input type="hidden" name="subscription_id" value="{{ $sub->id }}">
                                <x-form.field name="days" type="number" :label="__('platform.days')" value="7" required />
                                <x-form.field name="reason" :label="__('platform.reason')" />
                            </x-form>
                        @endif

                        <x-form :action="route('platform.subscriptions.activate', $tenant)" :title="$sub->status->value === 'active' ? __('platform.renew') : __('platform.activate_subscription')" :submit="$sub->status->value === 'active' ? __('platform.renew') : __('platform.activate_subscription')" class="shadow-none border border-line">
                            <input type="hidden" name="subscription_id" value="{{ $sub->id }}">
                            <input type="hidden" name="plan_id" value="{{ $sub->plan_id }}">
                            <x-form.field name="periods" type="number" :label="__('platform.periods')" value="1" required />
                        </x-form>

                        <x-form :action="route('platform.subscriptions.change-plan', $tenant)" :title="__('platform.change_plan')" :submit="__('platform.change_plan')" class="shadow-none border border-line">
                            <input type="hidden" name="subscription_id" value="{{ $sub->id }}">
                            <x-form.field name="plan_id" :label="__('platform.plan')">
                                <x-form.select name="plan_id" required :selected="$sub->plan_id" :options="$planOptions" />
                            </x-form.field>
                        </x-form>

                        <x-form :action="route('platform.subscriptions.payments', $tenant)" :title="__('platform.record_payment')" :submit="__('platform.record_payment')" class="shadow-none border border-line">
                            <input type="hidden" name="subscription_id" value="{{ $sub->id }}">
                            <x-form.field name="amount" :label="__('platform.amount').' ('.$sub->currency.')'" required />
                            <x-form.field name="method" :label="__('platform.method')">
                                <x-form.select name="method" required :options="collect(['bank_transfer', 'cash', 'cheque', 'card', 'other'])->mapWithKeys(fn ($m) => [$m => __('platform.methods.'.$m)])->all()" />
                            </x-form.field>
                            <x-form.field name="reference" :label="__('platform.reference')" />
                            <x-form.field name="paid_at" type="date" :label="__('platform.paid_on')" :value="now()->toDateString()" required />
                        </x-form>

                        @if ($sub->status->isLive())
                            <div class="lg:col-span-2">
                                <x-ui.action :action="route('platform.subscriptions.cancel', $tenant)" variant="danger" :fields="['subscription_id' => $sub->id]" :confirm="__('platform.cancel_subscription').'?'">{{ __('platform.cancel_subscription') }}</x-ui.action>
                            </div>
                        @endif
                    </div>
                @endadminCan
            </x-ui.card>
        @empty
            <x-ui.alert type="warning">{{ __('platform.no_subscription') }}</x-ui.alert>
        @endforelse
    </section>

    @adminCan('subscriptions.manage')
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-2" aria-label="{{ __('platform.add_plan') }}">
            <x-form :action="route('platform.subscriptions.grant-plan', $tenant)" :title="__('platform.add_plan')" :submit="__('platform.add_plan')">
                <p class="text-sm text-muted">{{ __('platform.add_plan_hint') }}</p>
                <x-form.field name="plan_id" :label="__('platform.plan')">
                    <x-form.select name="plan_id" required :options="$planOptions" />
                </x-form.field>
                <x-form.field name="periods" type="number" :label="__('platform.periods')" value="1" required />
            </x-form>

            <x-form :action="route('platform.subscriptions.grant-all', $tenant)" :title="__('platform.grant_all')" :submit="__('platform.grant_all')">
                <p class="text-sm text-muted">{{ __('platform.grant_all_hint') }}</p>
                <x-form.field name="periods" type="number" :label="__('platform.periods')" value="1" required />
            </x-form>
        </section>
    @endadminCan

    @adminCan('tenants.manage')
        @if ($tenant->status === 'active')
            <x-ui.action :action="route('platform.tenants.suspend', $tenant)" variant="danger" :confirm="__('platform.suspend').'?'">{{ __('platform.suspend') }}</x-ui.action>
        @else
            <x-ui.action :action="route('platform.tenants.activate', $tenant)">{{ __('platform.reactivate') }}</x-ui.action>
        @endif
    @endadminCan

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('platform.companies')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @foreach ($companies as $company)
                    <tr><x-table.td>{{ $company->name }}</x-table.td><x-table.td>{{ $company->country_code }}</x-table.td><x-table.td mono>{{ $company->base_currency }}</x-table.td></tr>
                @endforeach
            </x-table>
        </x-ui.card>

        <x-ui.card :title="__('platform.users')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @foreach ($users as $user)
                    <tr><x-table.td>{{ $user->name }}</x-table.td><x-table.td>{{ $user->email }}</x-table.td></tr>
                @endforeach
            </x-table>
        </x-ui.card>

        <x-ui.card :title="__('platform.history')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @forelse ($events as $event)
                    <tr>
                        <x-table.td>{{ __('subscriptions.events.'.$event->type) }} <span class="text-muted">· {{ $event->subscription->plan->localizedName() }}</span></x-table.td>
                        <x-table.td end>{{ $event->occurred_at->toDateTimeString() }}</x-table.td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" />
                @endforelse
            </x-table>
        </x-ui.card>

        <x-ui.card :title="__('platform.payments')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @forelse ($payments as $payment)
                    <tr>
                        <x-table.td><x-money :minor="$payment->amount_minor" :currency="$payment->currency" /> {{ $payment->currency }}</x-table.td>
                        <x-table.td>{{ __('platform.methods.'.$payment->method) }} {{ $payment->reference }} <span class="text-muted">· {{ $payment->subscription->plan->localizedName() }}</span></x-table.td>
                        <x-table.td end>{{ $payment->paid_at->toDateString() }}</x-table.td>
                    </tr>
                @empty
                    <x-table.empty :colspan="3" />
                @endforelse
            </x-table>
        </x-ui.card>
    </div>
</x-platform::layout>
