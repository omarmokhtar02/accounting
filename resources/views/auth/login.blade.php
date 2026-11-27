<x-guest-layout>
    <x-slot name="title">{{ __('app.auth.login_title') }}</x-slot>
    <x-slot name="subtitle">{{ __('app.auth.login_subtitle') }}</x-slot>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="text-sm text-primary-600 hover:text-primary-700 hover:underline" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>
            <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex items-center gap-2">
            <input id="remember_me" type="checkbox" class="rounded border-line-strong text-primary-600 shadow-sm focus:ring-primary-500" name="remember">
            <span class="text-sm text-muted">{{ __('Remember me') }}</span>
        </label>

        <x-primary-button class="w-full">{{ __('Log in') }}</x-primary-button>
    </form>

    @if (Route::has('register'))
        <x-slot name="footer">
            {{ __('app.auth.no_account') }}
            <a href="{{ route('register') }}" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">{{ __('app.auth.create_account') }}</a>
        </x-slot>
    @endif
</x-guest-layout>
