@props(['value' => null])

<x-form.label :value="$value" {{ $attributes }}>{{ $slot }}</x-form.label>
