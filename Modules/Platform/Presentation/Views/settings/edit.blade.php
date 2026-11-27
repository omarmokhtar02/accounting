<x-platform::layout>
    <x-slot name="header"><x-ui.page-header :title="__('platform.settings.title')" /></x-slot>

    <p class="text-sm text-muted">{{ __('platform.settings.help') }}</p>

    <x-form :action="route('platform.settings.update')" method="PUT" class="max-w-2xl">
        <x-form.field name="trial_days" type="number" :label="__('platform.settings.trial_days')" :value="$values['trial.days']" required />
        <x-form.field name="trial_plan_code" :label="__('platform.settings.trial_plan_code')">
            <x-form.select id="trial_plan_code" name="trial_plan_code" required :selected="old('trial_plan_code', $values['trial.plan_code'])"
                :options="$plans->mapWithKeys(fn ($p) => [$p->code => $p->localizedName()])->all()" />
        </x-form.field>
        <x-form.field name="subscription_readonly_grace_days" type="number" :label="__('platform.settings.readonly_grace_days')" :value="$values['subscription.readonly_grace_days']" required />
        <x-form.field name="billing_instructions_en" :label="__('platform.settings.billing_instructions_en')" :value="$values['billing.instructions_en']" />
        <x-form.field name="billing_instructions_ar" :label="__('platform.settings.billing_instructions_ar')" :value="$values['billing.instructions_ar']" dir="rtl" />
        <x-form.field name="billing_contact_email" type="email" :label="__('platform.settings.billing_contact_email')" :value="$values['billing.contact_email']" />
    </x-form>
</x-platform::layout>
