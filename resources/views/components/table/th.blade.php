@props(['end' => false])

<th {{ $attributes->merge(['class' => 'px-4 py-2 font-medium text-muted '.($end ? 'text-end' : 'text-start')]) }}>{{ $slot }}</th>
