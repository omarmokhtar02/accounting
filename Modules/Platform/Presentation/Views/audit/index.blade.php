<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="__('platform.audit.title')" /></x-slot>

    <form method="GET" class="flex items-end gap-3">
        <div class="w-64"><x-form.label :value="__('platform.audit.entity')" /><x-form.input name="entity" :value="$entity" /></div>
        <x-ui.button variant="secondary">{{ __('platform.filter') }}</x-ui.button>
    </form>

    <x-table>
        <x-slot name="head">
            <x-table.th>{{ __('platform.audit.when') }}</x-table.th>
            <x-table.th>{{ __('platform.audit.admin') }}</x-table.th>
            <x-table.th>{{ __('platform.audit.action') }}</x-table.th>
            <x-table.th>{{ __('platform.audit.entity') }}</x-table.th>
            <x-table.th>{{ __('platform.audit.details') }}</x-table.th>
        </x-slot>
        @forelse ($logs as $log)
            <tr>
                <x-table.td>{{ $log->occurred_at->toDateTimeString() }}</x-table.td>
                <x-table.td>{{ $log->admin?->name ?? __('platform.audit.system') }}</x-table.td>
                <x-table.td mono>{{ $log->action }}</x-table.td>
                <x-table.td>{{ $log->entity_type }} @if ($log->entity_id)#{{ $log->entity_id }}@endif</x-table.td>
                <x-table.td class="text-xs font-mono">{{ $log->data ? json_encode($log->data, JSON_UNESCAPED_UNICODE) : '' }}</x-table.td>
            </tr>
        @empty
            <x-table.empty :colspan="5" />
        @endforelse
    </x-table>
    {{ $logs->links() }}
</x-platform::layout>
