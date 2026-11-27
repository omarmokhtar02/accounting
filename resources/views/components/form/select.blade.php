@props(['options' => [], 'placeholder' => null, 'selected' => null])

<select {{ $attributes->merge(['class' => 'block w-full border-line-strong focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-sm']) }}>
    @if ($placeholder !== null)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $value => $label)
        <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
    @endforeach
    {{ $slot }}
</select>
