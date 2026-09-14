<template>
    <AppLayout>
        <UiPageHeader :title="$t('Content pages')" :subtitle="$t('Edit bilingual public pages using the advanced editor')">
            <template #actions>
                <button @click="openModal()" type="button" class="pgi-btn pgi-btn--solid">{{ $t('Add page') }}</button>
            </template>
        </UiPageHeader>

        <PageTable :rows="props.rows" :filters="props.filters" class="mt-6" />

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
    import AppLayout from "../Layout/App.vue";
    import PageTable from '@/Components/Tables/PageTable.vue';
    import { ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import FormModal from '@/Components/FormModalModified.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps({ rows: Object, filters: Object });
    const isOpenFormModal = ref(false);
    const label = ref('Add Page');
    const formUrl = ref('/admin/settings/pages');
    const formMethod = ref('post');

    const form = useForm({
        name_ar: null,
        name_en: null,
    });

    const formInputs = [
        {
            inputType: 'FormInput',
            name: 'name_ar',
            label: t('Name (Arabic)'),
            type: 'text',
            className: 'sm:col-span-3',
        },
        {
            inputType: 'FormInput',
            name: 'name_en',
            label: t('Name (English)'),
            type: 'text',
            className: 'sm:col-span-3',
        }
    ];

    const openModal = (key, formData = {}) => {
        form.name_ar = null;
        form.name_en = null;
        isOpenFormModal.value = true;
    }
</script>

<style scoped>
.pgi-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.pgi-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.pgi-btn--solid:hover {
    filter: brightness(1.05);
}
</style>
