@props(['title'])

<div class="flex items-center justify-between gap-4">
    <h2 class="font-semibold text-xl text-ink leading-tight">{{ $title }}</h2>
    @isset($actions)
        <div class="flex items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
