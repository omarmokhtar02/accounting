{{-- Data table. Use the "head" slot for <x-table.th> cells and the default slot for rows. --}}
<div {{ $attributes->merge(['class' => 'bg-card shadow sm:rounded-lg overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-line text-sm">
        @isset($head)
            <thead class="bg-surface"><tr>{{ $head }}</tr></thead>
        @endisset
        <tbody class="divide-y divide-line">{{ $slot }}</tbody>
    </table>
</div>
