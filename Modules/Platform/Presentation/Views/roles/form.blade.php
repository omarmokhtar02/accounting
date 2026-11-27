<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="$role->exists ? $role->localizedName() : __('platform.roles.new')" /></x-slot>

    <x-form :action="$role->exists ? route('platform.roles.update', $role) : route('platform.roles.store')" :method="$role->exists ? 'PUT' : 'POST'" class="max-w-3xl">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="name_en" :label="__('platform.plans.name_en')" :value="$role->name_en" required />
            <x-form.field name="name_ar" :label="__('platform.plans.name_ar')" :value="$role->name_ar" dir="rtl" required />
        </div>

        @foreach ($groups as $module => $keys)
            <fieldset class="border-b border-line pb-3">
                <legend class="font-semibold text-ink">{{ __('platform.modules.'.$module) }}</legend>
                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($keys as $key)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="{{ $key }}" class="rounded border-line-strong text-primary-600" @checked(in_array($key, old('permissions', $granted)))>
                            {{ __('platform.permission_labels.'.str_replace('.', '_', $key)) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
    </x-form>
</x-platform::layout>
