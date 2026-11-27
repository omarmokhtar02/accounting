<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.accounts')">
            <x-slot name="actions">
                <x-ui.button variant="secondary" :href="route('accounting.account-roles.index')">{{ __('accounting.account_roles') }}</x-ui.button>
                @can('accounts.create')
                    <x-ui.button variant="ghost" :href="route('accounting.accounts.create')">{{ __('accounting.new_account') }}</x-ui.button>
                @endcan
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <x-form.error :messages="$errors->get('account')" />

        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.code') }}</x-table.th>
                <x-table.th>{{ __('accounting.name') }}</x-table.th>
                <x-table.th>{{ __('accounting.type') }}</x-table.th>
                <x-table.th>{{ __('accounting.status') }}</x-table.th>
                <x-table.th />
            </x-slot>

            @forelse ($accounts as $account)
                <tr>
                    <x-table.td mono>{{ $account->code }}</x-table.td>
                    <x-table.td>{{ $account->localizedName() }}</x-table.td>
                    <x-table.td>{{ $account->type->label() }}</x-table.td>
                    <x-table.td><x-ui.badge :tone="$account->isActive() ? 'success' : 'neutral'">{{ $account->status->label() }}</x-ui.badge></x-table.td>
                    <x-table.td end>
                        <div class="flex justify-end gap-2">
                            @can('accounts.update')
                                <x-ui.button variant="ghost" size="sm" :href="route('accounting.accounts.edit', $account)">{{ __('admin.edit') }}</x-ui.button>
                            @endcan
                            @can('accounts.archive')
                                @if ($account->status->value === 'active')
                                    <x-ui.action :action="route('accounting.accounts.status', $account)" size="sm" variant="secondary" :fields="['status' => 'inactive']">{{ __('accounting.deactivate') }}</x-ui.action>
                                @elseif ($account->status->value === 'inactive')
                                    <x-ui.action :action="route('accounting.accounts.status', $account)" size="sm" variant="secondary" :fields="['status' => 'active']">{{ __('accounting.activate') }}</x-ui.action>
                                @endif
                                @if ($account->status->value !== 'archived')
                                    <x-ui.action :action="route('accounting.accounts.status', $account)" size="sm" variant="danger" :confirm="__('accounting.archive').'?'" :fields="['status' => 'archived']">{{ __('accounting.archive') }}</x-ui.action>
                                @endif
                            @endcan
                        </div>
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="5" />
            @endforelse
        </x-table>
        <div class="mt-4">{{ $accounts->links() }}</div>
    </div>
</x-app-layout>
