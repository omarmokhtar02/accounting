<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('subscriptions.billing')" />
    </x-slot>

    @php
        $heldPlanIds = $subscriptions->filter(fn ($s) => $s->status->isLive())->pluck('plan_id')->all();
        $money = fn ($minor, $currency) => (new \Modules\Shared\Domain\Money($minor, app(\Modules\Shared\Domain\Contracts\CurrencyRegistry::class)->get($currency)))->format().' '.$currency;
    @endphp

    <div class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('billing_notice'))
            <x-ui.alert :type="session('billing_notice') === 'blocked' ? 'danger' : 'warning'">
                {{ __('subscriptions.notice_'.session('billing_notice')) }}
            </x-ui.alert>
        @endif

        <section aria-labelledby="your-plans" class="space-y-4">
            <h2 id="your-plans" class="text-base font-semibold text-ink">{{ __('subscriptions.your_plans') }}</h2>

            @forelse ($subscriptions as $subscription)
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-ink">{{ $subscription->plan->localizedName() }}</h3>
                            <p class="mt-1 text-sm text-muted">
                                {{ $subscription->price_minor > 0 ? $money($subscription->price_minor, $subscription->currency).' / '.__('subscriptions.per_'.$subscription->interval) : __('subscriptions.not_priced') }}
                            </p>
                        </div>
                        <x-ui.badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-ui.badge>
                    </div>

                    @if ($subscription->accessEndsAt())
                        <p class="mt-3 text-sm text-muted">
                            {{ $subscription->status->value === 'trialing' ? __('subscriptions.trial_ends') : __('subscriptions.period_ends') }}:
                            <span class="text-ink">{{ $subscription->accessEndsAt()->toDateString() }}</span>
                            @if ($subscription->daysLeft() > 0) · {{ __('subscriptions.days_left', ['days' => $subscription->daysLeft()]) }} @endif
                        </p>
                    @endif
                </x-ui.card>
            @empty
                <x-ui.card><p class="text-muted">{{ __('subscriptions.no_subscription') }}</p></x-ui.card>
            @endforelse
        </section>

        <x-ui.card :title="__('subscriptions.usage')">
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-muted">{{ __('subscriptions.users') }}</dt>
                    <dd class="font-semibold">{{ $usage['users'] }} / {{ $limits['max_users'] ?? __('subscriptions.unlimited') }}</dd></div>
                <div><dt class="text-muted">{{ __('subscriptions.companies') }}</dt>
                    <dd class="font-semibold">{{ $usage['companies'] }} / {{ $limits['max_companies'] ?? __('subscriptions.unlimited') }}</dd></div>
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('subscriptions.available_plans')">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($plans as $plan)
                    <div class="rounded-xl border border-line p-4 {{ in_array($plan->id, $heldPlanIds, true) ? 'border-primary-200 bg-primary-50/50' : '' }}">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="font-semibold">{{ $plan->localizedName() }}</h4>
                            @if (in_array($plan->id, $heldPlanIds, true))
                                <x-ui.badge tone="primary">{{ __('subscriptions.included') }}</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-muted">{{ $plan->localizedDescription() }}</p>
                        <p class="mt-2 text-sm">{{ __('subscriptions.users') }}: {{ $plan->max_users ?? __('subscriptions.unlimited') }}</p>
                        <p class="text-sm">{{ __('subscriptions.companies') }}: {{ $plan->max_companies ?? __('subscriptions.unlimited') }}</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm">{{ $instructions }} @if ($contactEmail) <a class="text-primary-600 underline" href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> @endif</p>
        </x-ui.card>
    </div>
</x-app-layout>
