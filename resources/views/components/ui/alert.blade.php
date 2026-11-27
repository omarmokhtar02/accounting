@props(['type' => 'success'])

@php
$types = [
    'success' => 'bg-success-50 text-success-800',
    'danger' => 'bg-danger-50 text-danger-700',
    'warning' => 'bg-warning-50 text-warning-800',
];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-md p-3 text-sm '.($types[$type] ?? $types['success'])]) }} role="alert">
    {{ $slot }}
</div>
