@props(['end' => false, 'mono' => false])

<td {{ $attributes->merge(['class' => 'px-4 py-2 text-ink '.($end ? 'text-end ' : '').($mono ? 'font-mono' : '')]) }}>{{ $slot }}</td>
