<template>
    <AppLayout>
        <UiPageHeader :title="$t('Email templates')" :subtitle="$t('Configure email templates')" />
        <EmailTemplateTable :rows="props.rows" :filters="props.filters" class="mt-6" />
    </AppLayout>
</template>
<script setup>
    import AppLayout from "../Layout/App.vue";
    import EmailTemplateTable from '@/Components/Tables/EmailTemplateTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';

    const props = defineProps({ rows: Object, filters: Object });
</script>
