<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.account_roles')" />
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-muted">{{ __('accounting.account_roles_help') }}</p>
        <x-form.error :messages="$errors->get('role')" />

        @foreach ($roles as $role)
            <form method="POST" action="{{ route('accounting.account-roles.update') }}" id="role-{{ $role->value }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="role" value="{{ $role->value }}">
            </form>
        @endforeach

        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.role') }}</x-table.th>
                <x-table.th>{{ __('accounting.account') }}</x-table.th>
                <x-table.th />
            </x-slot>
            @foreach ($roles as $role)
                <tr>
                    <x-table.td>{{ $role->label() }}</x-table.td>
                    <x-table.td>
                        <x-form.select form="role-{{ $role->value }}" name="account_id" required :selected="$mapped[$role->value] ?? null" placeholder="{{ __('accounting.not_mapped') }}"
                            :options="$accounts->where('type', $role->expectedType())->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->localizedName()])->all()" />
                    </x-table.td>
                    <x-table.td end>
                        @can('accounts.update')
                            <x-ui.button size="sm" form="role-{{ $role->value }}">{{ __('accounting.save') }}</x-ui.button>
                        @endcan
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
    </div>
</x-app-layout>
