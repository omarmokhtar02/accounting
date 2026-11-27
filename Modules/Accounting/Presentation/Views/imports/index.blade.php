<x-app-layout>
    <x-slot name="header"><x-ui.page-header :title="__('accounting.import.title')" /></x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if ($report !== null)
            @if ($report->committed)
                <x-ui.alert type="success">
                    {{ __('accounting.import.done', ['created' => $report->created, 'skipped' => $report->skipped]) }}
                </x-ui.alert>
            @elseif ($report->hasErrors())
                <x-ui.alert type="danger">{{ __('accounting.import.has_errors', ['count' => count($report->errors)]) }}</x-ui.alert>

                <x-table>
                    <x-slot name="head">
                        <x-table.th>{{ __('accounting.import.row') }}</x-table.th>
                        <x-table.th>{{ __('accounting.import.problem') }}</x-table.th>
                    </x-slot>
                    @foreach (array_slice($report->errors, 0, 100) as $error)
                        <tr>
                            <x-table.td mono>{{ $error['row'] }}</x-table.td>
                            <x-table.td>{{ __($error['key'], $error['replace']) }}</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            @else
                <x-ui.alert type="success">{{ __('accounting.import.ready', ['rows' => $report->total, 'skipped' => $report->skipped]) }}</x-ui.alert>

                <x-form :action="route('accounting.imports.commit')" :submit="__('accounting.import.confirm')">
                    <input type="hidden" name="type" value="{{ $type }}">
                    <input type="hidden" name="token" value="{{ $token }}">
                    <p class="text-sm text-muted">{{ __('accounting.import.confirm_hint') }}</p>
                </x-form>
            @endif
        @endif

        @error('file')
            <x-ui.alert type="danger">{{ $message }}</x-ui.alert>
        @enderror

        <x-form :action="route('accounting.imports.preview')" enctype="multipart/form-data" :title="__('accounting.import.upload')" :submit="__('accounting.import.preview')">
            <div>
                <x-form.label :value="__('accounting.import.what')" />
                <x-form.select name="type" :selected="old('type', $type ?? null)"
                    :options="collect($importers)->mapWithKeys(fn ($i) => [$i->key() => __('accounting.import.types.'.$i->key())])->all()" />
            </div>
            <div>
                <x-form.label :value="__('accounting.import.file')" />
                <input type="file" name="file" accept=".csv,.xlsx" required class="block w-full text-sm">
            </div>
        </x-form>

        <x-ui.card :title="__('accounting.import.templates')">
            <ul class="space-y-2 text-sm">
                @foreach ($importers as $importer)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span>
                            <span class="font-medium text-ink">{{ __('accounting.import.types.'.$importer->key()) }}</span>
                            <span class="text-muted font-mono text-xs">{{ implode(', ', $importer->templateColumns()) }}</span>
                        </span>
                        <span class="flex gap-2">
                            <x-ui.button variant="ghost" size="sm" :href="route('accounting.imports.template', [$importer->key(), 'xlsx'])">Excel</x-ui.button>
                            <x-ui.button variant="ghost" size="sm" :href="route('accounting.imports.template', [$importer->key(), 'csv'])">CSV</x-ui.button>
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    </div>
</x-app-layout>
