{{-- Right side of the top bar: company switcher, language, user menu. Fed by CompanySwitcherComposer. --}}
@if ($switcherCompanies->count() > 1)
    <form method="POST" action="{{ route('company.switch') }}" class="hidden sm:block">
        @csrf
        <label class="sr-only" for="company_switch">{{ __('app.switch_company') }}</label>
        <div class="relative">
            <x-icon name="building" class="pointer-events-none absolute start-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
            <select id="company_switch" name="company_id" onchange="this.form.submit()"
                class="rounded-lg border-line-strong py-1.5 ps-8 pe-8 text-sm focus:border-primary-500 focus:ring-primary-500">
                @foreach ($switcherCompanies as $switchable)
                    <option value="{{ $switchable->id }}" @selected($activeCompany && $activeCompany->id === $switchable->id)>
                        {{ $switchable->localizedName() }} ({{ $switchable->country_code }})
                    </option>
                @endforeach
            </select>
        </div>
    </form>
@elseif ($activeCompany)
    <span class="hidden items-center gap-2 rounded-lg bg-surface px-3 py-1.5 text-sm text-ink sm:inline-flex">
        <x-icon name="building" class="h-4 w-4 text-muted" />
        {{ $activeCompany->localizedName() }}
        <span class="text-muted">· {{ $activeCompany->country_code }}</span>
    </span>
@endif

<x-dropdown align="right" width="w-56">
    <x-slot name="trigger">
        <button type="button" class="flex items-center gap-2 rounded-lg py-1 ps-1 pe-2 text-sm hover:bg-surface focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <span class="grid h-8 w-8 place-items-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700">{{ mb_strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}</span>
            <span class="hidden max-w-[10rem] truncate font-medium text-ink sm:block">{{ Auth::user()->name }}</span>
            <x-icon name="chevron" class="h-4 w-4 text-muted" />
        </button>
    </x-slot>

    <x-slot name="content">
        <div class="border-b border-line px-4 py-2 text-xs text-muted">{{ Auth::user()->email }}</div>
        <x-dropdown-link :href="route('profile.edit')">{{ __('Profile') }}</x-dropdown-link>
        <x-dropdown-link :href="route('locale.update', app()->getLocale() === 'ar' ? 'en' : 'ar')">
            {{ app()->getLocale() === 'ar' ? __('app.english') : __('app.arabic') }}
        </x-dropdown-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                {{ __('Log Out') }}
            </x-dropdown-link>
        </form>
    </x-slot>
</x-dropdown>
