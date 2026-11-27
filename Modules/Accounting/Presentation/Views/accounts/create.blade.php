<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('accounting.new_account')" />
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <x-form :action="route('accounting.accounts.store')">
            <x-form.field name="code" :label="__('accounting.code')" required />
            <x-form.field name="name" :label="__('accounting.name')" required />
            <x-form.field name="name_ar" :label="__('accounting.name_ar')" dir="rtl" />

            <x-form.field name="type" :label="__('accounting.type')">
                <x-form.select id="type" name="type" required :selected="old('type')"
                    :options="collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all()" />
            </x-form.field>

            <x-form.field name="parent_id" :label="__('accounting.parent')">
                <x-form.select id="parent_id" name="parent_id" placeholder="—" :selected="old('parent_id')"
                    :options="$parents->mapWithKeys(fn ($p) => [$p->id => $p->code.' — '.$p->localizedName()])->all()" />
            </x-form.field>

            <x-form.field name="cash_flow_category" :label="__('accounting.cash_flow_category')">
                <x-form.select id="cash_flow_category" name="cash_flow_category" :selected="old('cash_flow_category', 'none')"
                    :options="collect($categories)->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" />
            </x-form.field>

            <x-form.checkbox name="is_postable" :label="__('accounting.postable')" :checked="true" />
        </x-form>
    </div>
</x-app-layout>
