<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="__('platform.nav.tenants')" /></x-slot>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="w-64"><x-form.label :value="__('platform.search')" /><x-form.input name="q" :value="$search" /></div>
        <div class="w-48">
            <x-form.label :value="__('platform.status')" />
            <x-form.select name="status" :selected="$status" :placeholder="__('platform.all')"
                :options="['trialing' => __('subscriptions.status.trialing'), 'active' => __('subscriptions.status.active'), 'expired' => __('subscriptions.status.expired'), 'canceled' => __('subscriptions.status.canceled'), 'none' => __('platform.none')]" />
        </div>
        <x-ui.button variant="secondary">{{ __('platform.filter') }}</x-ui.button>
    </form>

    <x-table>
        <x-slot name="head">
            <x-table.th>{{ __('platform.tenant') }}</x-table.th>
            <x-table.th>{{ __('platform.plan') }}</x-table.th>
            <x-table.th>{{ __('platform.status') }}</x-table.th>
            <x-table.th>{{ __('platform.ends') }}</x-table.th>
            <x-table.th>{{ __('platform.created') }}</x-table.th>
            <x-table.th />
        </x-slot>
        @forelse ($tenants as $tenant)
            @php($subscription = $subscriptions[$tenant->id] ?? null)
            <tr>
                <x-table.td><a class="text-primary-600 hover:underline" href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->name }}</a></x-table.td>
                <x-table.td>
                    @forelse ($livePlans[$tenant->id] ?? [] as $planName)
                        <x-ui.badge tone="primary" class="me-1">{{ $planName }}</x-ui.badge>
                    @empty
                        {{ $subscription?->plan->localizedName() ?? '—' }}
                    @endforelse
                </x-table.td>
                <x-table.td>
                    @if ($tenant->status !== 'active')<x-ui.badge tone="danger">{{ __('platform.tenant_suspended_label') }}</x-ui.badge>@endif
                    @if ($subscription)<x-ui.badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-ui.badge>@endif
                </x-table.td>
                <x-table.td>{{ $subscription?->accessEndsAt()?->toDateString() ?? '—' }}</x-table.td>
                <x-table.td>{{ $tenant->created_at->toDateString() }}</x-table.td>
                <x-table.td end><x-ui.button variant="ghost" size="sm" :href="route('platform.tenants.show', $tenant)">{{ __('platform.view') }}</x-ui.button></x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="6" />
        @endforelse
    </x-table>
    {{ $tenants->links() }}
</x-platform::layout>
