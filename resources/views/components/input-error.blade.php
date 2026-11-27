@props(['messages' => null])

<x-form.error :messages="$messages" {{ $attributes }} />
