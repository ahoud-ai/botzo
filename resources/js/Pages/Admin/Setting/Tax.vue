<template>
    <AppLayout>
        <UiPageHeader :title="$t('Tax rates')" :subtitle="$t('Configure tax rates')">
            <template #actions>
                <button @click="openModal()" type="button" class="tax-btn tax-btn--solid">{{ $t('Add tax rate') }}</button>
            </template>
        </UiPageHeader>

        <UiSectionCard class="mt-6 mb-6" :title="$t('Tax calculation method')" :subtitle="$t('Choose whether tax should be calculated inclusively or exclusively')">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:max-w-md">
                <FormSelect v-model="form1.is_tax_inclusive" :name="''" :type="'text'" :options="taxCalculationOptions" :error="form1.errors.is_tax_inclusive" :class="'flex-1'"/>
                <button @click="submitForm1()" type="button" class="tax-btn tax-btn--ghost">{{ $t('Update') }}</button>
            </div>
        </UiSectionCard>

        <!-- Table Component-->
        <TaxTable :rows="props.rows" @edit="openModal" @delete="openAlert" />

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
    import { useForm } from "@inertiajs/vue3";
    import TaxTable from '@/Components/Tables/TaxTable.vue';
    import FormModal from '@/Components/FormModalModified.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps({ rows: Object, config: Object });
    const isOpenFormModal = ref(false);
    const label = ref('Add Tax Rate');
    const formUrl = ref('/admin/tax-rates');
    const formMethod = ref('post');

    const form = {
        name: null,
        type: null,
        percentage: null,
        status: null
    };

    const getValueByKey = (key) => {
        const found = props.config.find(item => item.key === key);
        return found ? found.value : '';
    };

    const form1 = useForm({
        is_tax_inclusive: getValueByKey('is_tax_inclusive'),
    });

    const openModal = (key, formData = {}) => {
        label.value = t('Add tax rate');
        formUrl.value = '/admin/tax-rates';
        formMethod.value = 'post';

        if (key != null) {
            label.value = t('Edit tax rate');
            formUrl.value = '/admin/tax-rates/' + key;
            formMethod.value = 'put';
            getRow();
        } else {
            form.name = null;
            form.percentage = null;
            form.status = null;
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
            // Keep existing form state when the tax row cannot be loaded.
        });
    }

    const taxCalculationOptions = ref([
        { value: '1', label: t('Inclusive') },
        { value: '0', label: t('Exclusive') }
    ]);

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
            name: 'percentage',
            label: t('Percentage'),
            type: 'number',
            className: 'sm:col-span-3',
        },
        {
            inputType: 'FormSelect',
            name: 'status',
            label: t('status'),
            options: [
            { value: 'active', label: t('Active') },
            { value: 'inactive', label: t('Inactive') },
            ],
            className: 'sm:col-span-3',
        },
    ];

    const submitForm1 = async () => {
        form1.put('/admin/settings', {
            preserveScroll: true,
        })
    };
</script>

<style scoped>
.tax-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease;
}

.tax-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.tax-btn--solid:hover {
    filter: brightness(1.05);
}

.tax-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.tax-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}
</style>

