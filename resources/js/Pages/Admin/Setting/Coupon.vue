<template>
    <AppLayout>
        <div class="flex justify-between">
            <div>
                <h2 class="text-xl mb-1">{{ $t('Coupons') }}</h2>
                <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                    <span class="ms-1 mt-1">{{ $t('Configure coupons') }}</span>
                </p>
            </div>
            <div>
                <button @click="openModal()" type="button" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Add coupon') }}</button>
            </div>
        </div>

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

