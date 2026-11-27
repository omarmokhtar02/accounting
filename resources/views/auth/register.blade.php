<x-guest-layout>
    <x-slot name="title">{{ __('app.auth.register_title') }}</x-slot>
    <x-slot name="subtitle">{{ __('app.auth.register_subtitle') }}</x-slot>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="mt-1" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="company_name" :value="__('app.company_name')" />
            <x-text-input id="company_name" class="mt-1" type="text" name="company_name" :value="old('company_name')" required />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="country_code" :value="__('app.country')" />
            <x-form.select id="country_code" name="country_code" class="mt-1" required :selected="old('country_code')"
                :options="collect($countries)->mapWithKeys(fn ($pack) => [$pack->profile()->code => (app()->getLocale() === 'ar' ? $pack->profile()->nameAr : $pack->profile()->nameEn).' ('.$pack->profile()->currency.')'])->all()" />
            <x-input-error :messages="$errors->get('country_code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="mt-1" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-1" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" class="mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <x-primary-button class="w-full">{{ __('Register') }}</x-primary-button>
    </form>

    <x-slot name="footer">
        {{ __('Already registered?') }}
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700 hover:underline">{{ __('Log in') }}</a>
    </x-slot>
</x-guest-layout>
