<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.new_journal_entry')" />
    </x-slot>

    @php
        $rows = max(count(old('lines', [])), 2);
        $accountOptions = $accounts->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->localizedName()])->all();
    @endphp

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8">
        <x-form :action="route('accounting.journal-entries.store')">
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-form.field name="entry_date" type="date" :label="__('accounting.date')" :value="now()->toDateString()" required />
                <x-form.field name="description" :label="__('accounting.description')" class="sm:col-span-2" required />
            </div>

            <x-form.error :messages="$errors->get('lines')" />

            <x-table class="shadow-none">
                <x-slot name="head">
                    <x-table.th>{{ __('accounting.account') }}</x-table.th>
                    <x-table.th>{{ __('accounting.debit') }}</x-table.th>
                    <x-table.th>{{ __('accounting.credit') }}</x-table.th>
                    <x-table.th>{{ __('accounting.currency') }}</x-table.th>
                    <x-table.th>{{ __('accounting.rate') }}</x-table.th>
                    <x-table.th>{{ __('accounting.description') }}</x-table.th>
                </x-slot>
                @for ($i = 0; $i < $rows; $i++)
                    <tr>
                        <x-table.td><x-form.select name="lines[{{ $i }}][account_id]" placeholder="" required :options="$accountOptions" :selected="old('lines.'.$i.'.account_id')" /></x-table.td>
                        <x-table.td><x-form.input name="lines[{{ $i }}][debit]" inputmode="decimal" :value="old('lines.'.$i.'.debit')" /></x-table.td>
                        <x-table.td><x-form.input name="lines[{{ $i }}][credit]" inputmode="decimal" :value="old('lines.'.$i.'.credit')" /></x-table.td>
                        <x-table.td><x-form.select name="lines[{{ $i }}][currency]" :options="array_combine($currencies, $currencies)" :selected="old('lines.'.$i.'.currency', $baseCurrency)" /></x-table.td>
                        <x-table.td><x-form.input name="lines[{{ $i }}][rate]" inputmode="decimal" :placeholder="__('accounting.rate_auto')" :value="old('lines.'.$i.'.rate')" /></x-table.td>
                        <x-table.td><x-form.input name="lines[{{ $i }}][description]" :value="old('lines.'.$i.'.description')" /></x-table.td>
                    </tr>
                @endfor
            </x-table>
        </x-form>
    </div>
</x-app-layout>
