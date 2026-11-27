@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'block w-full border-line-strong focus:border-primary-500 focus:ring-primary-500 rounded-md shadow-sm']) }}>
