<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.new_fiscal_year')" />
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <x-form :action="route('accounting.fiscal-years.store')">
            <x-form.field name="name" :label="__('accounting.name')" :value="date('Y')" required />
            <x-form.field name="starts_on" type="date" :label="__('accounting.starts_on')" required />
            <x-form.field name="ends_on" type="date" :label="__('accounting.ends_on')" required />
        </x-form>
    </div>
</x-app-layout>
