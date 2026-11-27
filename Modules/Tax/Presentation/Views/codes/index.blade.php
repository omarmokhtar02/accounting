<x-app-layout>
    <x-slot name="header"><x-ui.page-header :title="__('tax.codes_title')" /></x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-muted">{{ __('tax.managed_by_platform') }}</p>

        <x-table>
            <x-slot name="head">
                <x-table.th>{{ __('tax.code') }}</x-table.th>
                <x-table.th>{{ __('accounting.name') }}</x-table.th>
                <x-table.th>{{ __('accounting.type') }}</x-table.th>
                <x-table.th end>{{ __('tax.current_rate') }}</x-table.th>
                <x-table.th>{{ __('tax.next_change') }}</x-table.th>
            </x-slot>
            @forelse ($rows as $row)
                <tr>
                    <x-table.td mono>{{ $row['code']->code }}</x-table.td>
                    <x-table.td>{{ $row['code']->localizedName() }}</x-table.td>
                    <x-table.td>{{ $row['code']->kind->label() }}</x-table.td>
                    <x-table.td end mono>
                        @if ($row['current']){{ $row['current']->percent() }}%@else<x-ui.badge tone="warning">{{ __('tax.not_configured') }}</x-ui.badge>@endif
                    </x-table.td>
                    <x-table.td>
                        @if ($row['next']){{ $row['next']->effective_from->toDateString() }}: {{ $row['next']->toRate()->percent() }}%@else — @endif
                    </x-table.td>
                </tr>
            @empty
                <x-table.empty :colspan="5">{{ __('tax.no_taxes') }}</x-table.empty>
            @endforelse
        </x-table>
    </div>
</x-app-layout>
