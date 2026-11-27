<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.new_user')" />
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <x-form :action="route('admin.users.store')">
            <x-form.field name="name" :label="__('accounting.name')" required />
            <x-form.field name="email" type="email" :label="__('Email')" required />
            <x-form.field name="password" type="password" :label="__('Password')" required />

            <x-form.field name="role" :label="__('admin.role')">
                <x-form.select id="role" name="role" required :selected="old('role')"
                    :options="$roles->mapWithKeys(fn ($r) => [$r->name => __('admin.roles_names.'.$r->name)])->all()" />
            </x-form.field>

            <div>
                <x-form.label :value="__('admin.companies')" />
                <div class="mt-1 space-y-1">
                    @foreach ($companies as $company)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="company_ids[]" value="{{ $company->id }}" class="rounded border-line-strong text-primary-600" @checked(in_array($company->id, old('company_ids', [])))>
                            {{ $company->localizedName() }}
                        </label>
                    @endforeach
                </div>
                <x-form.error :messages="$errors->get('company_ids')" class="mt-1" />
            </div>
        </x-form>
    </div>
</x-app-layout>
