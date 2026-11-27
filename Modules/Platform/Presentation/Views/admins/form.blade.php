<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="$admin->exists ? __('platform.edit').' '.$admin->name : __('platform.admins.new')" /></x-slot>

    <x-form :action="$admin->exists ? route('platform.admins.update', $admin) : route('platform.admins.store')" :method="$admin->exists ? 'PUT' : 'POST'" class="max-w-xl">
        <x-form.field name="name" :label="__('platform.name')" :value="$admin->name" required />
        @unless ($admin->exists)
            <x-form.field name="email" type="email" :label="__('platform.email')" required />
        @endunless
        <x-form.field name="password" type="password" :label="__('platform.password')" :required="! $admin->exists" />
        <p class="text-xs text-muted">{{ __('platform.admins.password_hint') }}</p>
        <x-form.field name="admin_role_id" :label="__('platform.admins.role')">
            <x-form.select id="admin_role_id" name="admin_role_id" required :selected="old('admin_role_id', $admin->admin_role_id)" :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->localizedName()])->all()" />
        </x-form.field>
        <x-form.field name="locale" :label="__('platform.admins.language')">
            <x-form.select id="locale" name="locale" required :selected="old('locale', $admin->locale)" :options="['en' => 'English', 'ar' => 'العربية']" />
        </x-form.field>
        @if ($admin->exists)
            <x-form.checkbox name="is_active" :label="__('platform.admins.active')" :checked="$admin->is_active" />
        @endif
    </x-form>
</x-platform::layout>
