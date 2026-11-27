<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="__('tax.platform_title')" /></x-slot>

    <p class="text-sm text-muted">{{ __('tax.platform_help') }}</p>

    @forelse ($overview as $country)
        <x-ui.card :title="$country['name'].' ('.$country['country'].')'" :padded="false">
            @if ($country['codes'] === [])
                <p class="p-4 text-sm text-muted">{{ __('tax.no_taxes') }}</p>
            @endif

            @foreach ($country['codes'] as $row)
                <div class="border-t border-line first:border-t-0 p-4 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-mono font-semibold">{{ $row['code'] }}</span>
                            <span class="text-sm text-muted">— {{ $row['name'] }} ({{ $row['kind']->label() }})</span>
                        </div>
                        <div class="text-sm">
                            {{ __('tax.current_rate') }}:
                            @if ($row['current'])<span class="font-mono font-semibold">{{ $row['current']->percent() }}%</span>
                            @else<x-ui.badge tone="warning">{{ __('tax.not_configured') }}</x-ui.badge>@endif
                            @if ($row['next'])
                                · {{ __('tax.next_change') }}: <span class="font-mono">{{ $row['next']->effective_from->toDateString() }} → {{ $row['next']->toRate()->percent() }}%</span>
                            @endif
                        </div>
                    </div>

                    @if ($row['history']->isNotEmpty())
                        <x-table class="shadow-none">
                            @foreach ($row['history'] as $schedule)
                                <tr>
                                    <x-table.td mono>{{ $schedule->effective_from->toDateString() }}</x-table.td>
                                    <x-table.td mono>{{ $schedule->toRate()->percent() }}%</x-table.td>
                                    <x-table.td>{{ $schedule->note }}</x-table.td>
                                    <x-table.td end>
                                        @if ($schedule->effective_from->toDateString() > now()->toDateString())
                                            <x-ui.action :action="route('platform.tax-rates.destroy', $schedule)" method="DELETE" variant="danger" size="sm" :confirm="__('platform.delete').'?'">{{ __('platform.delete') }}</x-ui.action>
                                        @endif
                                    </x-table.td>
                                </tr>
                            @endforeach
                        </x-table>
                    @endif

                    <form method="POST" action="{{ route('platform.tax-rates.store') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        <input type="hidden" name="country" value="{{ $country['country'] }}">
                        <input type="hidden" name="code" value="{{ $row['code'] }}">
                        <div class="w-32"><x-form.label :value="__('tax.new_rate_percent')" /><x-form.input name="percent" required placeholder="14" /></div>
                        <div class="w-44"><x-form.label :value="__('tax.effective_from')" /><x-form.input name="effective_from" type="date" :value="now()->toDateString()" required /></div>
                        <div class="w-64"><x-form.label :value="__('tax.note')" /><x-form.input name="note" /></div>
                        <x-ui.button size="sm">{{ __('tax.schedule_rate') }}</x-ui.button>
                    </form>
                </div>
            @endforeach
        </x-ui.card>
    @empty
        <p class="text-muted">{{ __('tax.no_taxes') }}</p>
    @endforelse
</x-platform::layout>
