<template>
    <AppLayout>
        <div class="mvr-page ui-page ui-fade-up text-[var(--ui-text)]">
            <div class="mvr-page__inner">
                <UiPageHeader :title="$t('Meta verifications')" :subtitle="$t('Track WhatsApp Business verification requests from clients and visitors in one place.')" />

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 mvr-kpi-row">
                    <UiStatCard class="ui-kpi--compact mvr-kpi mvr-kpi--primary" :title="$t('Total requests')" :value="summary?.total ?? 0">
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 3h10a2 2 0 0 1 2 2v14l-2-1l-2 1l-2-1l-2 1l-2-1l-2 1V5a2 2 0 0 1 2-2m0 2v10.76l.4-.2l1.6-.8l2 1l2-1l2 1l1.6-.8l.4.2V5zm2 2h6v2H9zm0 4h6v2H9z"/></svg>
                        </template>
                    </UiStatCard>
                    <UiStatCard class="ui-kpi--compact mvr-kpi mvr-kpi--secondary" :title="$t('In progress')" :value="summary?.in_progress ?? 0">
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2m1 5v4.586l2.707 2.707l-1.414 1.414l-3.293-3.293V7z"/></svg>
                        </template>
                    </UiStatCard>
                    <UiStatCard class="ui-kpi--compact mvr-kpi mvr-kpi--success" :title="$t('Approved')" :value="summary?.approved ?? 0">
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2 4 6v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V6zm-1.2 13.2L6.6 11l1.4-1.4l2.8 2.8l5.4-5.4L17.6 8.4z"/></svg>
                        </template>
                    </UiStatCard>
                    <UiStatCard class="ui-kpi--compact mvr-kpi mvr-kpi--danger" :title="$t('Rejected')" :value="summary?.rejected ?? 0">
                        <template #icon>
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2L1 21h22zm0 4.84L19.53 19H4.47zM11 10h2v5h-2zm0 6h2v2h-2z"/></svg>
                        </template>
                    </UiStatCard>
                </div>

                <MetaVerificationTable :rows="rows" :filters="filters" />
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
    import AppLayout from "../Layout/App.vue";
    import UiPageHeader from "@/Components/UI/UiPageHeader.vue";
    import UiStatCard from "@/Components/UI/UiStatCard.vue";
    import MetaVerificationTable from "@/Components/Tables/MetaVerificationTable.vue";
    import { defineProps } from "vue";

    defineProps({
        title: { type: String },
        rows: { type: Object },
        filters: { type: Object },
        summary: { type: Object, default: () => ({}) },
    });
</script>

<style scoped>
.mvr-page {
    min-height: 100%;
    padding: 1.5rem 2rem;
}

.mvr-page__inner {
    width: 100%;
    max-width: 1680px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.mvr-kpi-row :deep(.mvr-kpi) {
    position: relative;
    overflow: hidden;
    transition: transform 180ms ease, box-shadow 180ms ease;
}

.mvr-kpi-row :deep(.mvr-kpi)::before {
    content: '';
    position: absolute;
    inset-inline: 0;
    top: 0;
    height: 3px;
    background: var(--mvr-kpi-tone);
}

.mvr-kpi-row :deep(.mvr-kpi:hover) {
    transform: translateY(-2px);
    box-shadow: var(--ui-shadow-1);
}

.mvr-kpi-row :deep(.mvr-kpi--primary) { --mvr-kpi-tone: var(--ui-primary); }
.mvr-kpi-row :deep(.mvr-kpi--secondary) { --mvr-kpi-tone: var(--ui-secondary); }
.mvr-kpi-row :deep(.mvr-kpi--success) { --mvr-kpi-tone: var(--ui-success); }
.mvr-kpi-row :deep(.mvr-kpi--danger) { --mvr-kpi-tone: var(--ui-danger); }
</style>
