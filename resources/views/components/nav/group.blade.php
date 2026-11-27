@props(['label' => null])

{{-- A labelled block of sidebar links. Empty groups (no permission for any link) render nothing. --}}
@if (trim((string) $slot) !== '')
    <div class="space-y-1">
        @if ($label)
            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-sidebar-muted">{{ $label }}</p>
        @endif
        {{ $slot }}
    </div>
@endif
