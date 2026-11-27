@props(['tone' => 'neutral'])

@php
$tones = [
    'neutral' => 'bg-surface text-muted',
    'success' => 'bg-success-50 text-success-800',
    'warning' => 'bg-warning-50 text-warning-800',
    'danger' => 'bg-danger-50 text-danger-700',
    'primary' => 'bg-primary-50 text-primary-700',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium '.($tones[$tone] ?? $tones['neutral'])]) }}>{{ $slot }}</span>
