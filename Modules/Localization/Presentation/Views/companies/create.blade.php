<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('app.new_company')" />
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <x-form :action="route('localization.companies.store')">
            <x-form.field name="name" :label="__('accounting.name')" required />
            <x-form.field name="name_ar" :label="__('accounting.name_ar')" dir="rtl" />
            <x-form.field name="country_code" :label="__('app.country')">
                <x-form.select id="country_code" name="country_code" required :selected="old('country_code')"
                    :options="collect($countries)->mapWithKeys(fn ($pack) => [$pack->profile()->code => (app()->getLocale() === 'ar' ? $pack->profile()->nameAr : $pack->profile()->nameEn).' ('.$pack->profile()->currency.' — '.$pack->profile()->accountingStandard.')'])->all()" />
            </x-form.field>
        </x-form>
    </div>
</x-app-layout>
