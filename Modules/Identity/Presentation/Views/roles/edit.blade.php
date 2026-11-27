<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('admin.roles_names.'.$role->name)" />
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8">
        <x-form :action="route('admin.roles.update', $role)" method="PUT">
            @foreach ($groups as $module => $permissions)
                <fieldset class="border-b border-line pb-3">
                    <legend class="font-semibold text-ink">{{ __('admin.modules.'.$module) }}</legend>
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach ($permissions as $permission)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="permissions[]" value="{{ $permission }}" class="rounded border-line-strong text-primary-600" @checked(in_array($permission, old('permissions', $granted)))>
                                {{ __('admin.permission_labels.'.str_replace('.', '_', $permission)) }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </x-form>
    </div>
</x-app-layout>
