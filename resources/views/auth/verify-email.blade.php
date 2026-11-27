<x-guest-layout>
    <x-slot name="title">{{ __('app.auth.verify_title') }}</x-slot>
    <x-slot name="subtitle">{{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}</x-slot>

    @if (session('status') == 'verification-link-sent')
        <x-ui.alert class="mb-4">{{ __('A new verification link has been sent to the email address you provided during registration.') }}</x-ui.alert>
    @endif

    <div class="flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>{{ __('Resend Verification Email') }}</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="text-sm text-muted hover:text-ink hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
