@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])

{{-- Label + input + validation error in one place. Pass a slot to supply a custom control (select, textarea). --}}
<div {{ $attributes->only('class') }}>
    <x-form.label :for="$name" :value="$label" />
    @if ($slot->isEmpty())
        <x-form.input :id="$name" :name="$name" :type="$type" :value="old($name, $value)" :required="$required" class="mt-1" {{ $attributes->except('class') }} />
    @else
        <div class="mt-1">{{ $slot }}</div>
    @endif
    <x-form.error :messages="$errors->get($name)" class="mt-1" />
</div>
