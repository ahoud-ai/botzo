<template>
    <AppLayout>
        <UiPageHeader :title="$t('Billing info')" :subtitle="$t('Configure the information to be seen in your invoices')" />

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard>
                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput v-model="form.billing_name" :name="$t('Vendor name')" :error="form.errors.billing_name" :type="'text'" :class="'sm:col-span-2'"/>
                    <FormInput v-model="form.invoice_prefix" :name="$t('Invoice prefix')" :error="form.errors.invoice_prefix" :type="'text'"/>
                    <FormInput v-model="form.billing_tax_id" :name="$t('Tax number')" :error="form.errors.billing_tax_id" :type="'text'"/>
                    <FormInput v-model="form.billing_phone_1" :name="$t('Phone')" :error="form.errors.billing_phone_1" :type="'text'"/>
                    <FormInput v-model="form.billing_phone_2" :name="$t('Phone 2')" :error="form.errors.billing_phone_2" :type="'text'"/>
                    <FormInput v-model="form.billing_address" :name="$t('Address')" :error="form.errors.billing_address" :type="'text'" :class="'sm:col-span-2'"/>
                    <FormInput v-model="form.billing_city" :name="$t('City')" :error="form.errors.billing_city" :type="'text'"/>
                    <FormInput v-model="form.billing_state" :name="$t('State')" :error="form.errors.billing_state" :type="'text'"/>
                    <FormInput v-model="form.billing_postal_code" :name="$t('Zip code')" :error="form.errors.billing_postal_code" :type="'text'"/>
                    <FormInput v-model="form.billing_country" :name="$t('Country')" :error="form.errors.billing_country" :type="'text'"/>
                </div>
            </UiSectionCard>

            <div class="flex items-center justify-end">
                <button type="submit" class="bl-btn bl-btn--solid" :disabled="isLoading">
                    <svg v-if="isLoading" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import { useForm } from "@inertiajs/vue3";
    import { defineProps, ref } from 'vue';
    import FormInput from '@/Components/FormInput.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const props = defineProps({
        config: {
            type: Object,
            required: true
        }
    });

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const isLoading = ref(false);
    const form = useForm({
        billing_name: getValueByKey('billing_name'),
        invoice_prefix: getValueByKey('invoice_prefix'),
        billing_tax_id: getValueByKey('billing_tax_id'),
        billing_phone_1: getValueByKey('billing_phone_1'),
        billing_phone_2: getValueByKey('billing_phone_2'),
        billing_address: getValueByKey('billing_address'),
        billing_city: getValueByKey('billing_city'),
        billing_state: getValueByKey('billing_state'),
        billing_postal_code: getValueByKey('billing_postal_code'),
        billing_country: getValueByKey('billing_country'),
    })

    const submitForm = async () => {
        form.put('/admin/settings?type=billing', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.bl-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.bl-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.bl-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.bl-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
