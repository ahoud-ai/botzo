<template>
    <AppLayout>
        <UiPageHeader :title="$t('Languages & translations')" :subtitle="$t('Add, edit & translate languages')">
            <template #actions>
                <button v-if="adminCan('languages', 'create')" @click="openModal()" type="button" class="lng-btn lng-btn--solid">{{ $t('Add language') }}</button>
            </template>
        </UiPageHeader>

        <!-- Table Component-->
        <LangTable :rows="props.rows" :defaultLanguage="props.default_language" @edit="openModal" class="mt-6" />

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
    import AppLayout from "./../Layout/App.vue";
    import axios from "axios";
    import { ref } from 'vue';
    import LangTable from '@/Components/Tables/LangTable.vue';
    import FormModal from '@/Components/FormModalModified.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useI18n } from 'vue-i18n';
    import { useAdminPermission } from "@/Composables/useAdminPermission";
    const { t } = useI18n();
    const { adminCan } = useAdminPermission();

    const props = defineProps(['rows', 'config', 'default_language']);
    const isOpenFormModal = ref(false);
    const label = ref('Add language');
    const formUrl = ref('/admin/languages');
    const formMethod = ref('post');

    const form = {
        name: null,
        code: null,
        status: null,
        is_rtl: false,
    };

    const openModal = (key, formData = {}) => {
        label.value = t('Add language');
        formUrl.value = '/admin/languages';
        formMethod.value = 'post';

        if (key != null) {
            label.value = t('Edit language');
            formUrl.value = '/admin/languages/' + key;
            formMethod.value = 'put';
            getRow();
        } else {
            form.name = null;
            form.code = null;
            form.status = null;
            form.is_rtl = false;
            isOpenFormModal.value = true;
        }
    }

    function getRow() {
        axios.get(formUrl.value).then((response) => {
            const { data } = response;
            for (const key in data.item) {
                if (Object.prototype.hasOwnProperty.call(form, key)) {
                    form[key] = data.item[key];
                }
            }
            isOpenFormModal.value = true;
        })
        .catch(() => {
            // Keep existing form state when the language cannot be loaded.
        });
    }

    const formInputs = [
        {
            inputType: 'FormInput',
            name: 'name',
            label: t('Name'),
            type: 'text',
            className: 'sm:col-span-6',
        },
        {
            inputType: 'FormInput',
            name: 'code',
            label: t('Code'),
            type: 'text',
            className: 'sm:col-span-3',
        },
        {
            inputType: 'FormSelect',
            name: 'status',
            label: t('Status'),
            options: [
                { value: 'active', label: t('Active') },
                { value: 'inactive', label: t('Inactive') },
            ],
            className: 'sm:col-span-3',
        },
        {
            inputType: 'FormSelect',
            name: 'is_rtl',
            label: t('Right to Left'),
            options: [
                { value: false, label: t('False') },
                { value: true, label: t('True') },
            ],
            className: 'sm:col-span-6',
        }
    ];
</script>

<style scoped>
.lng-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.lng-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.lng-btn--solid:hover {
    filter: brightness(1.05);
}
</style>
