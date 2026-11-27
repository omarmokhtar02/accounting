@props(['label', 'value', 'icon' => 'chart', 'tone' => 'primary', 'stat' => null])

@php
$tones = [
    'primary' => 'bg-primary-50 text-primary-600',
    'success' => 'bg-success-50 text-success-600',
    'warning' => 'bg-warning-50 text-warning-600',
    'danger' => 'bg-danger-50 text-danger-600',
    'info' => 'bg-info-50 text-info-600',
    'neutral' => 'bg-surface text-muted',
];
@endphp

{{-- A single KPI: coloured icon chip, big number, small label. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-xl border border-line bg-card p-4 shadow-sm']) }}>
    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg {{ $tones[$tone] ?? $tones['primary'] }}">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <div class="min-w-0">
        <div class="truncate text-2xl font-semibold leading-tight text-ink" data-stat="{{ $stat }}">{{ $value }}</div>
        <div class="truncate text-xs text-muted">{{ $label }}</div>
    </div>
</div>
