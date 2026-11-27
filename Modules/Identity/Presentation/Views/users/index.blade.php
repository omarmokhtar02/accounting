<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.users')">
            <x-slot name="actions">
                <x-ui.button variant="ghost" :href="route('admin.users.create')">{{ __('admin.new_user') }}</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.name') }}</x-table.th>
                <x-table.th>{{ __('Email') }}</x-table.th>
                <x-table.th>{{ __('admin.role') }}</x-table.th>
                <x-table.th>{{ __('admin.companies') }}</x-table.th>
            </x-slot>
            @forelse ($users as $user)
                <tr>
                    <x-table.td>{{ $user->name }}</x-table.td>
                    <x-table.td>{{ $user->email }}</x-table.td>
                    <x-table.td>
                        @foreach ($user->roles as $role)
                            <x-ui.badge tone="primary">{{ __('admin.roles_names.'.$role->name) }}</x-ui.badge>
                        @endforeach
                    </x-table.td>
                    <x-table.td>{{ $user->companies->map->localizedName()->join(', ') }}</x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4" />
            @endforelse
        </x-table>
    </div>
</x-app-layout>
