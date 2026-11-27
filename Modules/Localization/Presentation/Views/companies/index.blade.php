<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('app.companies')">
            <x-slot name="actions">
                @can('companies.manage')
                    <x-ui.button variant="ghost" :href="route('localization.companies.create')">{{ __('app.new_company') }}</x-ui.button>
                @endcan
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.name') }}</x-table.th>
                <x-table.th>{{ __('app.country') }}</x-table.th>
                <x-table.th>{{ __('app.currency') }}</x-table.th>
                <x-table.th>{{ __('app.country_pack_version') }}</x-table.th>
            </x-slot>
            @forelse ($companies as $company)
                @php($profile = $countries[$company->country_code]->profile())
                <tr>
                    <x-table.td>{{ $company->localizedName() }}</x-table.td>
                    <x-table.td>{{ app()->getLocale() === 'ar' ? $profile->nameAr : $profile->nameEn }}</x-table.td>
                    <x-table.td mono>{{ $company->base_currency }}</x-table.td>
                    <x-table.td mono>{{ $company->country_pack_version }}</x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="4" />
            @endforelse
        </x-table>
    </div>
</x-app-layout>
