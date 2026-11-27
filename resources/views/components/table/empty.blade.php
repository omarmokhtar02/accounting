@props(['colspan' => 1])

<tr><td colspan="{{ $colspan }}" class="px-4 py-6 text-center text-muted">{{ $slot->isEmpty() ? __('accounting.no_records') : $slot }}</td></tr>
