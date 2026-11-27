@props(['action', 'method' => 'POST', 'variant' => 'primary', 'size' => 'md', 'confirm' => null, 'fields' => []])

<form method="POST" action="{{ $action }}" {{ $attributes }}
    @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif
    @foreach ($fields as $name => $value)
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endforeach
    <x-ui.button :variant="$variant" :size="$size">{{ $slot }}</x-ui.button>
</form>
