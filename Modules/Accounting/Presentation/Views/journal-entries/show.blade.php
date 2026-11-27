<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.journal_entries').' '.($entry->number ?? '#'.$entry->id)">
            <x-slot name="actions">
                <x-ui.badge :tone="$entry->status->tone()">{{ $entry->status->label() }}</x-ui.badge>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <x-form.error :messages="$errors->get('journal')" />

        <x-ui.card class="space-y-2 text-sm">
            <div>{{ __('accounting.date') }}: {{ $entry->entry_date->toDateString() }}</div>
            <div>{{ __('accounting.description') }}: {{ $entry->description }}</div>
            <div>{{ __('accounting.created_by') }}: {{ $entry->creator?->name }}</div>
            @if ($entry->reverses)
                <div>{{ __('accounting.reverses') }}:
                    <a class="text-primary-600 hover:underline" href="{{ route('accounting.journal-entries.show', $entry->reverses) }}">{{ $entry->reverses->number }}</a>
                </div>
            @endif
            @if ($entry->reversedBy)
                <div>{{ __('accounting.reversed_by') }}:
                    <a class="text-primary-600 hover:underline" href="{{ route('accounting.journal-entries.show', $entry->reversedBy) }}">{{ $entry->reversedBy->number }}</a>
                </div>
            @endif
        </x-ui.card>

        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.account') }}</x-table.th>
                <x-table.th end>{{ __('accounting.debit') }}</x-table.th>
                <x-table.th end>{{ __('accounting.credit') }}</x-table.th>
            </x-slot>
            @foreach ($entry->lines as $line)
                <tr>
                    <x-table.td>
                        {{ $line->account->code }} — {{ $line->account->localizedName() }}
                        @if ($line->txn_currency)
                            <div class="text-xs text-muted font-mono">
                                <x-money :minor="($line->txn_debit ?? $line->txn_credit)" :currency="$line->txn_currency" /> × {{ \Modules\Shared\Domain\ExchangeRate::plain((string) $line->exchange_rate) }}
                            </div>
                        @endif
                    </x-table.td>
                    <x-table.td end mono><x-money :minor="$line->debit" :currency="$entry->currency" blank-zero /></x-table.td>
                    <x-table.td end mono><x-money :minor="$line->credit" :currency="$entry->currency" blank-zero /></x-table.td>
                </tr>
            @endforeach
        </x-table>

        @if ($entry->status === \Modules\Accounting\Domain\Enums\JournalStatus::Draft)
            @can('journals.post')
                <x-ui.action :action="route('accounting.journal-entries.post', $entry)">{{ __('accounting.post') }}</x-ui.action>
            @endcan
        @elseif ($entry->status === \Modules\Accounting\Domain\Enums\JournalStatus::Posted)
            @can('journals.reverse')
                <x-form :action="route('accounting.journal-entries.reverse', $entry)" :submit="__('accounting.reverse')">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-form.field name="reversal_date" type="date" :label="__('accounting.reversal_date')" :value="now()->toDateString()" required />
                        <x-form.field name="reason" :label="__('accounting.reason')" />
                    </div>
                </x-form>
            @endcan
        @endif

        <x-ui.card :title="__('accounting.audit_trail')" class="text-sm">
            <ul class="space-y-1">
                @foreach ($audit as $log)
                    <li>{{ $log->occurred_at->toDateTimeString() }} — {{ $log->action }}@if ($log->reason) ({{ $log->reason }})@endif</li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</x-app-layout>
