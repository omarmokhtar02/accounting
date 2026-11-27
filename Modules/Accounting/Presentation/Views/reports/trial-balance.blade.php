<x-app-layout>
    <x-slot name="header"><x-ui.page-header :title="__('accounting.trial_balance')" /></x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-44"><x-form.label :value="__('accounting.from')" /><x-form.input type="date" name="from" :value="$from->toDateString()" /></div>
            <div class="w-44"><x-form.label :value="__('accounting.to')" /><x-form.input type="date" name="to" :value="$to->toDateString()" /></div>
            <label class="flex items-center gap-2 text-sm pb-2">
                <input type="hidden" name="hide_zero" value="0">
                <input type="checkbox" name="hide_zero" value="1" class="rounded border-line-strong" @checked($hideZero)> {{ __('accounting.hide_zero') }}
            </label>
            <x-ui.button variant="secondary">{{ __('accounting.run_report') }}</x-ui.button>
            @can('reports.export')
                <x-ui.button variant="ghost" :href="request()->fullUrlWithQuery(['format' => 'xlsx'])">Excel</x-ui.button>
                <x-ui.button variant="ghost" :href="request()->fullUrlWithQuery(['format' => 'csv'])">CSV</x-ui.button>
            @endcan
        </form>

        @if (! $report->isBalanced())
            <x-ui.alert type="danger">{{ __('accounting.report_not_balanced') }}</x-ui.alert>
        @endif

        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.code') }}</x-table.th>
                <x-table.th>{{ __('accounting.name') }}</x-table.th>
                <x-table.th end>{{ __('accounting.opening_debit') }}</x-table.th>
                <x-table.th end>{{ __('accounting.opening_credit') }}</x-table.th>
                <x-table.th end>{{ __('accounting.debit') }}</x-table.th>
                <x-table.th end>{{ __('accounting.credit') }}</x-table.th>
                <x-table.th end>{{ __('accounting.closing_debit') }}</x-table.th>
                <x-table.th end>{{ __('accounting.closing_credit') }}</x-table.th>
            </x-slot>
            @forelse ($report->rows as $row)
                <tr>
                    <x-table.td mono>{{ $row->code }}</x-table.td>
                    <x-table.td>
                        <a class="text-primary-600 hover:underline" href="{{ route('accounting.reports.general-ledger', ['account_id' => $row->accountId, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $row->localizedName() }}</a>
                    </x-table.td>
                    <x-table.td end mono><x-money :minor="$row->openingDebit()" :currency="$report->currency" blank-zero /></x-table.td>
                    <x-table.td end mono><x-money :minor="$row->openingCredit()" :currency="$report->currency" blank-zero /></x-table.td>
                    <x-table.td end mono><x-money :minor="$row->debit" :currency="$report->currency" blank-zero /></x-table.td>
                    <x-table.td end mono><x-money :minor="$row->credit" :currency="$report->currency" blank-zero /></x-table.td>
                    <x-table.td end mono><x-money :minor="$row->closingDebit()" :currency="$report->currency" blank-zero /></x-table.td>
                    <x-table.td end mono><x-money :minor="$row->closingCredit()" :currency="$report->currency" blank-zero /></x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="8" />
            @endforelse
            @if ($report->rows !== [])
                <tr class="bg-surface font-semibold">
                    <x-table.td />
                    <x-table.td>{{ __('accounting.total') }}</x-table.td>
                    <x-table.td end mono><x-money :minor="array_sum(array_map(fn ($r) => $r->openingDebit(), $report->rows))" :currency="$report->currency" /></x-table.td>
                    <x-table.td end mono><x-money :minor="array_sum(array_map(fn ($r) => $r->openingCredit(), $report->rows))" :currency="$report->currency" /></x-table.td>
                    <x-table.td end mono data-total="debit"><x-money :minor="$report->totalDebit()" :currency="$report->currency" /></x-table.td>
                    <x-table.td end mono data-total="credit"><x-money :minor="$report->totalCredit()" :currency="$report->currency" /></x-table.td>
                    <x-table.td end mono><x-money :minor="$report->totalClosingDebit()" :currency="$report->currency" /></x-table.td>
                    <x-table.td end mono><x-money :minor="$report->totalClosingCredit()" :currency="$report->currency" /></x-table.td>
                </tr>
            @endif
        </x-table>
    </div>
</x-app-layout>
