<x-guest-layout>
    <x-slot name="title">{{ __('app.auth.forgot_title') }}</x-slot>
    <x-slot name="subtitle">{{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}</x-slot>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="w-full">{{ __('Email Password Reset Link') }}</x-primary-button>
    </form>

    <x-slot name="footer">
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">{{ __('Log in') }}</a>
    </x-slot>
</x-guest-layout>
