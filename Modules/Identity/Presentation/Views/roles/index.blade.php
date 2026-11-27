<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.roles')" />
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('admin.role') }}</x-table.th>
                <x-table.th>{{ __('admin.permissions') }}</x-table.th>
                <x-table.th />
            </x-slot>
            @foreach ($roles as $role)
                <tr>
                    <x-table.td>{{ __('admin.roles_names.'.$role->name) }}</x-table.td>
                    <x-table.td>{{ $role->permissions_count }}</x-table.td>
                    <x-table.td end>
                        @if ($role->name === 'owner')
                            <span class="text-muted text-xs">{{ __('admin.owner_locked') }}</span>
                        @else
                            <x-ui.button variant="ghost" size="sm" :href="route('admin.roles.edit', $role)">{{ __('admin.edit') }}</x-ui.button>
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-app-layout>
