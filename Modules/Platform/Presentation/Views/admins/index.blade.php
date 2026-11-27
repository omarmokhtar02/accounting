<x-platform::layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('platform.admins.title')">
            <x-slot name="actions"><x-ui.button variant="ghost" :href="route('platform.admins.create')">{{ __('platform.admins.new') }}</x-ui.button></x-slot>
        </x-ui.page-header>
    </x-slot>

    <x-table>
        <x-slot name="head">
            <x-table.th>{{ __('platform.name') }}</x-table.th>
            <x-table.th>{{ __('platform.email') }}</x-table.th>
            <x-table.th>{{ __('platform.admins.role') }}</x-table.th>
            <x-table.th>{{ __('platform.status') }}</x-table.th>
            <x-table.th>{{ __('platform.admins.last_login') }}</x-table.th>
            <x-table.th />
        </x-slot>
        @foreach ($admins as $admin)
            <tr>
                <x-table.td>{{ $admin->name }}</x-table.td>
                <x-table.td>{{ $admin->email }}</x-table.td>
                <x-table.td><x-ui.badge tone="primary">{{ $admin->role->localizedName() }}</x-ui.badge></x-table.td>
                <x-table.td><x-ui.badge :tone="$admin->is_active ? 'success' : 'neutral'">{{ $admin->is_active ? __('platform.admins.active') : '—' }}</x-ui.badge></x-table.td>
                <x-table.td>{{ $admin->last_login_at?->toDateTimeString() ?? '—' }}</x-table.td>
                <x-table.td end><x-ui.button variant="ghost" size="sm" :href="route('platform.admins.edit', $admin)">{{ __('platform.edit') }}</x-ui.button></x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-platform::layout>
