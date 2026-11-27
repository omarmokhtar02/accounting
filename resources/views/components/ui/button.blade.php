@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])

@php
$variants = [
    'primary' => 'bg-primary-600 text-white border-transparent hover:bg-primary-700 focus:ring-primary-500',
    'secondary' => 'bg-card text-ink border-line-strong shadow-sm hover:bg-surface focus:ring-primary-500',
    'danger' => 'bg-danger-600 text-white border-transparent hover:bg-danger-700 focus:ring-danger-500',
    'ghost' => 'bg-transparent text-primary-600 border-transparent hover:bg-primary-50 focus:ring-primary-500',
];
$sizes = [
    'sm' => 'px-2.5 py-1 text-xs',
    'md' => 'px-4 py-2 text-sm',
];
$classes = 'inline-flex items-center justify-center gap-2 rounded-md border font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 '
    .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
