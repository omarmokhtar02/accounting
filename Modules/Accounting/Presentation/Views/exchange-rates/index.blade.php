<x-app-layout>
    <x-slot name="header"><x-ui.page-header :title="__('accounting.fx_title')" /></x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <x-ui.alert>{{ session('status') }}</x-ui.alert>
        @endif

        @can('accounts.update')
            <x-form :action="route('accounting.exchange-rates.store')" :title="__('accounting.fx_add')">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-form.label :value="__('accounting.currency')" />
                        <x-form.select name="currency_code" :options="$currencies" :selected="old('currency_code')" />
                        <x-form.error :messages="$errors->get('currency_code')" />
                    </div>
                    <x-form.field name="rate_date" type="date" :label="__('accounting.fx_date')" :value="now()->toDateString()" required />
                    <x-form.field name="rate" inputmode="decimal" :label="__('accounting.rate')" required />
                </div>
                <p class="text-sm text-muted">{{ __('accounting.fx_rate_hint', ['base' => $baseCurrency]) }} {{ __('accounting.fx_note') }}</p>
            </x-form>
        @endcan

        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.fx_date') }}</x-table.th>
                <x-table.th>{{ __('accounting.currency') }}</x-table.th>
                <x-table.th end>{{ __('accounting.rate') }} ({{ $baseCurrency }})</x-table.th>
            </x-slot>
            @forelse ($rates as $rate)
                <tr>
                    <x-table.td mono>{{ $rate->rate_date->toDateString() }}</x-table.td>
                    <x-table.td>{{ $rate->currency_code }}</x-table.td>
                    <x-table.td end mono>{{ \Modules\Shared\Domain\ExchangeRate::plain((string) $rate->rate) }}</x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="3" />
            @endforelse
        </x-table>

        {{ $rates->links() }}
    </div>
</x-app-layout>
