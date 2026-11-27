<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="$plan->exists ? __('platform.edit').' '.$plan->code : __('platform.plans.title').' — '.__('platform.new')" /></x-slot>

    <x-form :action="$plan->exists ? route('platform.plans.update', $plan) : route('platform.plans.store')" :method="$plan->exists ? 'PUT' : 'POST'" class="max-w-3xl">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-form.field name="code" :label="__('platform.plans.code')" :value="$plan->code" required />
            <x-form.field name="sort_order" type="number" :label="__('platform.plans.sort_order')" :value="$plan->sort_order ?? 0" />
            <x-form.field name="name_en" :label="__('platform.plans.name_en')" :value="$plan->name_en" required />
            <x-form.field name="name_ar" :label="__('platform.plans.name_ar')" :value="$plan->name_ar" dir="rtl" required />
            <x-form.field name="description_en" :label="__('platform.plans.description_en')" :value="$plan->description_en" />
            <x-form.field name="description_ar" :label="__('platform.plans.description_ar')" :value="$plan->description_ar" dir="rtl" />
            <x-form.field name="price" :label="__('platform.plans.price')" :value="$price" required />
            <x-form.field name="currency" :label="__('platform.plans.currency')" :value="$plan->currency" required maxlength="3" />
            <x-form.field name="interval" :label="__('platform.plans.interval')">
                <x-form.select id="interval" name="interval" required :selected="old('interval', $plan->interval)" :options="['month' => __('platform.plans.month'), 'year' => __('platform.plans.year')]" />
            </x-form.field>
            <x-form.field name="trial_days" type="number" :label="__('platform.plans.trial_days')" :value="$plan->trial_days" />
            <x-form.field name="max_users" type="number" :label="__('platform.plans.max_users')" :value="$plan->max_users" />
            <x-form.field name="max_companies" type="number" :label="__('platform.plans.max_companies')" :value="$plan->max_companies" />
        </div>
        <x-form.checkbox name="is_active" :label="__('platform.plans.is_active')" :checked="$plan->is_active" />
    </x-form>
</x-platform::layout>
