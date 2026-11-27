<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.journal_entries')">
            <x-slot name="actions">
                @can('journals.create')
                    <x-ui.button variant="ghost" :href="route('accounting.journal-entries.create')">{{ __('accounting.new_journal_entry') }}</x-ui.button>
                @endcan
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('accounting.number') }}</x-table.th>
                <x-table.th>{{ __('accounting.date') }}</x-table.th>
                <x-table.th>{{ __('accounting.description') }}</x-table.th>
                <x-table.th end>{{ __('accounting.total') }}</x-table.th>
                <x-table.th>{{ __('accounting.status') }}</x-table.th>
            </x-slot>

            @forelse ($entries as $entry)
                <tr>
                    <x-table.td mono>
                        <a class="text-primary-600 hover:underline" href="{{ route('accounting.journal-entries.show', $entry) }}">{{ $entry->number ?? '#'.$entry->id }}</a>
                    </x-table.td>
                    <x-table.td>{{ $entry->entry_date->toDateString() }}</x-table.td>
                    <x-table.td>{{ $entry->description }}</x-table.td>
                    <x-table.td end mono><x-money :minor="(int) $entry->total_debit" :currency="$entry->currency" /></x-table.td>
                    <x-table.td><x-ui.badge :tone="$entry->status->tone()">{{ $entry->status->label() }}</x-ui.badge></x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="5" />
            @endforelse
        </x-table>
        <div class="mt-4">{{ $entries->links() }}</div>
    </div>
</x-app-layout>
