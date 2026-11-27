<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="__('platform.dashboard')" /></x-slot>

    @php
        // [stat key, value, icon, tone]
        $tiles = [
            ['tenants', $stats['tenants']['total'], 'building', 'primary'],
            ['trialing', $stats['subscriptions']['trialing'], 'clock', 'info'],
            ['active', $stats['subscriptions']['active'], 'bolt', 'success'],
            ['expired', $stats['subscriptions']['expired'], 'clock', 'danger'],
            ['canceled', $stats['subscriptions']['canceled'], 'close', 'neutral'],
            ['suspended', $stats['tenants']['suspended'], 'shield', 'warning'],
            ['no_subscription', $stats['tenants']['no_subscription'], 'card', 'warning'],
            ['signups_30d', $stats['signups_30d'], 'plus', 'success'],
            ['users', $stats['users'], 'users', 'primary'],
            ['companies', $stats['companies'], 'building', 'primary'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($tiles as [$key, $value, $icon, $tone])
            <x-ui.stat :stat="$key" :value="$value" :label="__('platform.stats.'.$key)" :icon="$icon" :tone="$tone" />
        @endforeach
    </div>

    <x-ui.card :title="__('platform.stats.mrr')">
        @forelse ($stats['mrr_display'] as $line)
            <div class="text-xl font-semibold font-mono">{{ $line }}</div>
        @empty
            <p class="text-muted text-sm">{{ __('platform.stats.no_revenue') }}</p>
        @endforelse
    </x-ui.card>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <x-ui.card :title="__('platform.stats.trials_ending')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @forelse ($stats['trials_ending'] as $subscription)
                    <tr>
                        <x-table.td><a class="text-primary-600 hover:underline" href="{{ route('platform.tenants.show', $subscription->tenant_id) }}">{{ $subscription->tenant->name }}</a></x-table.td>
                        <x-table.td end>{{ $subscription->trial_ends_at->toDateString() }}</x-table.td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" />
                @endforelse
            </x-table>
        </x-ui.card>

        <x-ui.card :title="__('platform.stats.recent_signups')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @forelse ($stats['recent_signups'] as $tenant)
                    <tr>
                        <x-table.td><a class="text-primary-600 hover:underline" href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a></x-table.td>
                        <x-table.td end>{{ $tenant->created_at->toDateString() }}</x-table.td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" />
                @endforelse
            </x-table>
        </x-ui.card>

        <x-ui.card :title="__('platform.stats.recently_expired')" :padded="false">
            <x-table class="shadow-none rounded-none">
                @forelse ($stats['recently_expired'] as $subscription)
                    <tr>
                        <x-table.td><a class="text-primary-600 hover:underline" href="{{ route('platform.tenants.show', $subscription->tenant_id) }}">{{ $subscription->tenant->name }}</a></x-table.td>
                        <x-table.td end>{{ $subscription->accessEndsAt()?->toDateString() }}</x-table.td>
                    </tr>
                @empty
                    <x-table.empty :colspan="2" />
                @endforelse
            </x-table>
        </x-ui.card>
    </div>
</x-platform::layout>
