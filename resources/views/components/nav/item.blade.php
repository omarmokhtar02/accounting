@props(['href', 'icon' => 'grid', 'active' => false, 'badge' => null])

{{-- One sidebar link. The active page gets the brand colour and an accent bar on the leading edge. --}}
<a href="{{ $href }}" @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400',
        'bg-primary-600 text-white shadow-sm' => $active,
        'text-sidebar-text hover:bg-sidebar-hover hover:text-white' => ! $active,
    ]) }}>
    <x-icon :name="$icon" @class(['w-5 h-5 shrink-0', 'text-white' => $active, 'text-sidebar-muted group-hover:text-white' => ! $active]) />
    <span class="truncate">{{ $slot }}</span>
    @if ($badge !== null)
        <span class="ms-auto rounded-full bg-white/15 px-2 py-0.5 text-xs">{{ $badge }}</span>
    @endif
</a>
