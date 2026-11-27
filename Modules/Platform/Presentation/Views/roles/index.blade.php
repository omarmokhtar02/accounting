<x-platform::layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('platform.roles.title')">
            <x-slot name="actions"><x-ui.button variant="ghost" :href="route('platform.roles.create')">{{ __('platform.roles.new') }}</x-ui.button></x-slot>
        </x-ui.page-header>
    </x-slot>

    <x-table>
        <x-slot name="head">
            <x-table.th>{{ __('platform.name') }}</x-table.th>
            <x-table.th>{{ __('platform.roles.permissions') }}</x-table.th>
            <x-table.th>{{ __('platform.roles.users') }}</x-table.th>
            <x-table.th />
        </x-slot>
        @foreach ($roles as $role)
            <tr>
                <x-table.td>{{ $role->localizedName() }} @if ($role->is_system)<x-ui.badge>{{ __('platform.roles.system') }}</x-ui.badge>@endif</x-table.td>
                <x-table.td>{{ $role->isSuperAdmin() ? __('subscriptions.unlimited') : $role->permissions_count }}</x-table.td>
                <x-table.td>{{ $role->users_count }}</x-table.td>
                <x-table.td end>
                    <div class="flex justify-end gap-2">
                        @unless ($role->isSuperAdmin())
                            <x-ui.button variant="ghost" size="sm" :href="route('platform.roles.edit', $role)">{{ __('platform.edit') }}</x-ui.button>
                        @endunless
                        @unless ($role->is_system)
                            <x-ui.action :action="route('platform.roles.destroy', $role)" method="DELETE" variant="danger" size="sm" :confirm="__('platform.delete').'?'">{{ __('platform.delete') }}</x-ui.action>
                        @endunless
                    </div>
                </x-table.td>
            </tr>
        @endforeach
    </x-table>
</x-platform::layout>
