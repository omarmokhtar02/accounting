<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.fiscal_years')">
            <x-slot name="actions">
                @can('periods.manage')
                    <x-ui.button variant="ghost" :href="route('accounting.fiscal-years.create')">{{ __('accounting.new_fiscal_year') }}</x-ui.button>
                @endcan
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <x-form.error :messages="$errors->get('period')" />

        @forelse ($years as $year)
            <x-ui.card :padded="false">
                <x-slot name="title">{{ $year->name }} ({{ $year->starts_on->toDateString() }} → {{ $year->ends_on->toDateString() }})</x-slot>
                <x-table class="shadow-none rounded-none">
                    <x-slot name="head">
                        <x-table.th>#</x-table.th>
                        <x-table.th>{{ __('accounting.starts_on') }}</x-table.th>
                        <x-table.th>{{ __('accounting.ends_on') }}</x-table.th>
                        <x-table.th>{{ __('accounting.status') }}</x-table.th>
                        <x-table.th />
                    </x-slot>
                    @foreach ($year->periods as $period)
                        <tr>
                            <x-table.td>{{ $period->number }}</x-table.td>
                            <x-table.td>{{ $period->starts_on->toDateString() }}</x-table.td>
                            <x-table.td>{{ $period->ends_on->toDateString() }}</x-table.td>
                            <x-table.td>
                                <x-ui.badge :tone="$period->isOpen() ? 'success' : 'neutral'">{{ __('accounting.period_status.'.$period->status) }}</x-ui.badge>
                            </x-table.td>
                            <x-table.td end>
                                @if ($period->isOpen())
                                    @can('periods.close')
                                        <x-ui.action :action="route('accounting.periods.close', $period)" variant="danger" size="sm">{{ __('accounting.close_period') }}</x-ui.action>
                                    @endcan
                                @endif
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-ui.card>
        @empty
            <p class="text-muted">{{ __('accounting.no_records') }}</p>
        @endforelse
    </div>
</x-app-layout>
