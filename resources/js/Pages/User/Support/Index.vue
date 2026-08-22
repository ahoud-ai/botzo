<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Support tickets')" :subtitle="$t('Have an issue? Create a ticket and one of our reps will be in touch')">
                <template #actions>
                    <Link href="/support/create" class="support-action-btn support-action-btn--solid">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                        {{ $t('Create ticket') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <UiStatCard class="support-kpi support-kpi--total" :title="$t('Total tickets')" :value="stats?.total ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="support-kpi support-kpi--open" :title="$t('open')" :value="stats?.open ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2S2 6.477 2 12s4.477 10 10 10Zm0-14v4l3 3"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="support-kpi support-kpi--pending" :title="$t('pending')" :value="stats?.pending ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-9-9c2.52 0 4.85.84 6.7 2.26M21 3v5h-5"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="support-kpi support-kpi--resolved" :title="$t('resolved')" :value="stats?.resolved ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m5 13l4 4L19 7"/></svg>
                    </template>
                </UiStatCard>
            </div>

            <!-- Table Component-->
            <TicketTable :rows="props.rows" :filters="props.filters" class="mt-6"/>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link } from "@inertiajs/vue3";
    import TicketTable from '@/Components/Tables/TicketTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiStatCard from '@/Components/UI/UiStatCard.vue';

    const props = defineProps({ rows: Object, filters: Object, stats: Object });
</script>

<style scoped>
.support-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
    border-radius: 0.7rem;
    padding: 0.6rem 1.1rem;
}

.support-action-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    transition: filter 160ms ease;
}

.support-action-btn--solid:hover {
    filter: brightness(1.05);
}

.support-kpi :deep(.ui-kpi-icon) {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.85rem;
}

.support-kpi :deep(.ui-kpi-icon svg) {
    width: 1.1rem;
    height: 1.1rem;
}

.support-kpi--total :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-secondary) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-secondary) 30%, transparent);
}
.support-kpi--total :deep(.ui-kpi-icon svg) { color: var(--ui-secondary) !important; }

.support-kpi--open :deep(.ui-kpi-icon) {
    background: rgba(21, 93, 252, 0.12);
    border-color: rgba(21, 93, 252, 0.22);
}
.support-kpi--open :deep(.ui-kpi-icon svg) { color: #155dfc !important; }

.support-kpi--pending :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-warning) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-warning) 30%, transparent);
}
.support-kpi--pending :deep(.ui-kpi-icon svg) { color: var(--ui-warning) !important; }

.support-kpi--resolved :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-success) 30%, transparent);
}
.support-kpi--resolved :deep(.ui-kpi-icon svg) { color: var(--ui-success) !important; }
</style>
