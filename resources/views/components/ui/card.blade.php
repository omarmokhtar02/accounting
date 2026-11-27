@props(['title' => null, 'padded' => true])

<div {{ $attributes->merge(['class' => 'bg-card shadow sm:rounded-lg overflow-hidden']) }}>
    @if ($title)
        <div class="border-b border-line px-4 py-3 font-semibold text-ink">{{ $title }}</div>
    @endif
    <div @class(['p-4 sm:p-6' => $padded])>{{ $slot }}</div>
</div>
