@props(['action', 'method' => 'POST', 'title' => null, 'submit' => null])

{{-- Standard create/update form: card, CSRF, method spoofing and a submit button. --}}
<form method="POST" action="{{ $action }}" {{ $attributes->merge(['class' => 'bg-card shadow sm:rounded-lg p-6 space-y-4']) }}>
    @csrf
    @if (! in_array(strtoupper($method), ['GET', 'POST'], true))
        @method($method)
    @endif

    @if ($title)
        <h3 class="font-semibold text-ink">{{ $title }}</h3>
    @endif

    {{ $slot }}

    <div class="flex items-center gap-2 pt-2">
        <x-ui.button>{{ $submit ?? __('accounting.save') }}</x-ui.button>
        @isset($extra){{ $extra }}@endisset
    </div>
</form>
