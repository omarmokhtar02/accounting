@props(['value' => null])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-ink']) }}>
    {{ $value ?? $slot }}
</label>
