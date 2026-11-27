@props(['name', 'label', 'checked' => false])

<label class="inline-flex items-center gap-2">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) {{ $attributes->merge(['class' => 'rounded border-line-strong text-primary-600 focus:ring-primary-500']) }}>
    <span class="text-sm text-ink">{{ $label }}</span>
</label>
