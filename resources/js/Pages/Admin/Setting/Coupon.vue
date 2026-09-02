<template>
    <AppLayout>
        <UiPageHeader :title="$t('Coupons')" :subtitle="$t('Configure coupons')">
            <template #actions>
                <button @click="openModal()" type="button" class="cpn-btn cpn-btn--solid">{{ $t('Add coupon') }}</button>
            </template>
        </UiPageHeader>

        <!-- Table Component-->
        <CouponTable :rows="props.rows" @edit="openModal" @delete="openAlert" />

        <!-- Form Modal Component-->
        <FormModal
            v-model="isOpenFormModal"
            :label="label"
            :url="formUrl"
            :form="form"
            :formInputs="formInputs"
            :formMethod="formMethod"
            @close="isOpenFormModal = false"
        />
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./Layout/App.vue";
    import axios from "axios";
    import { ref } from 'vue';
    import CouponTable from '@/Components/Tables/CouponTable.vue';
    import FormModal from '@/Components/FormModalModified.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps({ rows: Object });
    const isOpenFormModal = ref(false);
    const label = ref('Add Coupon');
    const formUrl = ref('/admin/coupons');
    const formMethod = ref('post');

    const form = {
        name: null,
        code: null,
        percentage: null,
        quantity: null,
    };

    const openModal = (key, formData = {}) => {
        label.value = t('Add coupon');
        formUrl.value = '/admin/coupons';
        formMethod.value = 'post';

        if (key != null) {
            label.value = t('Edit coupon');
            formUrl.value = '/admin/coupons/' + key;
            formMethod.value = 'put';
            getRow();
        } else {
            form.name = null;
            form.code = null;
            form.percentage = null;
            form.quantity = null;
            isOpenFormModal.value = true;
        }
    }

    function getRow() {
        axios.get(formUrl.value).then((response) => {
            const { data } = response;
            for (const key in data.item) {
                if (form.hasOwnProperty(key)) {
                    form[key] = data.item[key];
                }
            }
            isOpenFormModal.value = true;
        })
        .catch(() => {
            // Keep existing form state when the coupon cannot be loaded.
        });
    }

    const formInputs = [
        {
            inputType: 'FormInput',
            name: 'name',
            label: t('name'),
            type: 'text',
            className: 'sm:col-span-6',
        },
        {
            inputType: 'FormInput',
            name: 'code',
            label: t('Coupon code'),
            type: 'text',
            className: 'sm:col-span-3',
        },
        {
            inputType: 'FormInput',
            name: 'percentage',
            label: t('Percentage'),
            type: 'number',
            className: 'sm:col-span-3',
        },
        {
            inputType: 'FormInput',
            name: 'quantity',
            label: t('Quantity'),
            type: 'number',
            className: 'sm:col-span-6',
        },
    ];
</script>

<style scoped>
.cpn-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.cpn-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.cpn-btn--solid:hover {
    filter: brightness(1.05);
}
</style>

