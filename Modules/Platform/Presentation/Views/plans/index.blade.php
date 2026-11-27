<x-platform::layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('platform.plans.title')">
            <x-slot name="actions"><x-ui.button variant="ghost" :href="route('platform.plans.create')">{{ __('platform.new') }}</x-ui.button></x-slot>
        </x-ui.page-header>
    </x-slot>

    <p class="text-sm text-muted">{{ __('platform.plans.help') }}</p>

    <x-table>
        <x-slot name="head">
            <x-table.th>{{ __('platform.plans.code') }}</x-table.th>
            <x-table.th>{{ __('platform.name') }}</x-table.th>
            <x-table.th end>{{ __('platform.plans.price') }}</x-table.th>
            <x-table.th>{{ __('platform.plans.max_users') }}</x-table.th>
            <x-table.th>{{ __('platform.plans.max_companies') }}</x-table.th>
            <x-table.th>{{ __('platform.plans.subscribers') }}</x-table.th>
            <x-table.th>{{ __('platform.status') }}</x-table.th>
            <x-table.th />
        </x-slot>
        @foreach ($plans as $plan)
            <tr>
                <x-table.td mono>{{ $plan->code }}</x-table.td>
                <x-table.td>{{ $plan->localizedName() }}</x-table.td>
                <x-table.td end mono><x-money :minor="$plan->price_minor" :currency="$plan->currency" /> {{ $plan->currency }} / {{ __('platform.plans.'.$plan->interval) }}</x-table.td>
                <x-table.td>{{ $plan->max_users ?? __('subscriptions.unlimited') }}</x-table.td>
                <x-table.td>{{ $plan->max_companies ?? __('subscriptions.unlimited') }}</x-table.td>
                <x-table.td>{{ $plan->subscriptions_count }}</x-table.td>
                <x-table.td><x-ui.badge :tone="$plan->is_active ? 'success' : 'neutral'">{{ $plan->is_active ? __('platform.admins.active') : '—' }}</x-ui.badge></x-table.td>
                <x-table.td end><x-ui.button variant="ghost" size="sm" :href="route('platform.plans.edit', $plan)">{{ __('platform.edit') }}</x-ui.button></x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-platform::layout>
