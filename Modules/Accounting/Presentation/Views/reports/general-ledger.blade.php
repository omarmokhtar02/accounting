<x-app-layout>
    <x-slot name="header"><x-ui.page-header :title="__('accounting.general_ledger')" /></x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-72">
                <x-form.label :value="__('accounting.account')" />
                <x-form.select name="account_id" required :selected="request('account_id')" placeholder=""
                    :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->code.' — '.$a->localizedName()])->all()" />
            </div>
            <div class="w-44"><x-form.label :value="__('accounting.from')" /><x-form.input type="date" name="from" :value="$from->toDateString()" /></div>
            <div class="w-44"><x-form.label :value="__('accounting.to')" /><x-form.input type="date" name="to" :value="$to->toDateString()" /></div>
            <x-ui.button variant="secondary">{{ __('accounting.run_report') }}</x-ui.button>
            @if ($result)
                @can('reports.export')
                    <x-ui.button variant="ghost" :href="request()->fullUrlWithQuery(['format' => 'xlsx'])">Excel</x-ui.button>
                    <x-ui.button variant="ghost" :href="request()->fullUrlWithQuery(['format' => 'csv'])">CSV</x-ui.button>
                @endcan
            @endif
        </form>

        @if ($result)
            @if ($result->truncated)
                <x-ui.alert type="warning">{{ __('accounting.report_truncated', ['limit' => \Modules\Accounting\Application\Reporting\GeneralLedgerReport::DEFAULT_LIMIT]) }}</x-ui.alert>
            @endif

            <x-table>
                <x-slot name="head">
                    <x-table.th>{{ __('accounting.date') }}</x-table.th>
                    <x-table.th>{{ __('accounting.number') }}</x-table.th>
                    <x-table.th>{{ __('accounting.description') }}</x-table.th>
                    <x-table.th end>{{ __('accounting.debit') }}</x-table.th>
                    <x-table.th end>{{ __('accounting.credit') }}</x-table.th>
                    <x-table.th end>{{ __('accounting.balance') }}</x-table.th>
                </x-slot>
                <tr class="bg-surface">
                    <x-table.td colspan="5">{{ __('accounting.opening_balance') }}</x-table.td>
                    <x-table.td end mono data-balance="opening"><x-money :minor="$result->openingBalance" :currency="$result->currency" /></x-table.td>
                </tr>
                @forelse ($result->lines as $line)
                    <tr>
                        <x-table.td>{{ $line->date }}</x-table.td>
                        <x-table.td mono><a class="text-primary-600 hover:underline" href="{{ route('accounting.journal-entries.show', $line->journalEntryId) }}">{{ $line->number ?? '#'.$line->journalEntryId }}</a></x-table.td>
                        <x-table.td>{{ $line->lineDescription ?? $line->entryDescription }}</x-table.td>
                        <x-table.td end mono><x-money :minor="$line->debit" :currency="$result->currency" blank-zero /></x-table.td>
                        <x-table.td end mono><x-money :minor="$line->credit" :currency="$result->currency" blank-zero /></x-table.td>
                        <x-table.td end mono><x-money :minor="$line->runningBalance" :currency="$result->currency" /></x-table.td>
                    </tr>
                @empty
                    <x-table.empty :colspan="6" />
                @endforelse
                <tr class="bg-surface font-semibold">
                    <x-table.td colspan="3">{{ __('accounting.closing_balance') }}</x-table.td>
                    <x-table.td end mono><x-money :minor="$result->totalDebit()" :currency="$result->currency" /></x-table.td>
                    <x-table.td end mono><x-money :minor="$result->totalCredit()" :currency="$result->currency" /></x-table.td>
                    <x-table.td end mono data-balance="closing"><x-money :minor="$result->closingBalance()" :currency="$result->currency" /></x-table.td>
                </tr>
            </x-table>
        @endif
    </div>
</x-app-layout>
