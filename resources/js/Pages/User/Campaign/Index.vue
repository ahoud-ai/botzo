<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Campaigns')" :subtitle="$t('Add campaigns')">
                <template #title>
                    <div class="flex items-baseline gap-x-2">
                        <span>{{ $t('Campaigns') }}</span>
                        <span class="campaigns-count-pill">{{ props.rows?.meta?.total ?? props.rows?.data?.length ?? 0 }}</span>
                    </div>
                </template>
                <template #actions>
                    <Link v-if="canAdd" href="/campaigns/create" data-tour="campaigns-create" class="campaign-create-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
                        {{ $t('Create campaign') }}
                    </Link>
                    <button type="button" v-else disabled class="campaign-create-btn campaign-create-btn--disabled">{{ $t('Create campaign') }}</button>
                </template>
            </UiPageHeader>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <UiStatCard class="campaign-kpi campaign-kpi--total" :title="$t('Total campaigns')" :value="stats?.total ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h7"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="campaign-kpi campaign-kpi--ongoing" :title="$t('ongoing')" :value="stats?.ongoing ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m13 2-10 12h7l-1 8 10-12h-7l1-8Z"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="campaign-kpi campaign-kpi--scheduled" :title="$t('scheduled')" :value="stats?.scheduled ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-9-9c2.52 0 4.85.84 6.7 2.26M21 3v5h-5"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="campaign-kpi campaign-kpi--completed" :title="$t('completed')" :value="stats?.completed ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m5 13 4 4L19 7"/></svg>
                    </template>
                </UiStatCard>
            </div>

            <!-- Table Component-->
            <CampaignTable :rows="props.rows" :filters="props.filters" class="mt-6"/>
        </div>
    </AppLayout>
</template>

<style scoped>
.campaigns-count-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 1.4rem;
    height: 1.4rem;
    padding: 0 0.45rem;
    border-radius: 999px;
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    color: var(--ui-muted);
    font-size: 0.75rem;
    font-weight: 700;
}

.campaign-create-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 0.7rem;
    padding: 0.6rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.campaign-create-btn:hover {
    filter: brightness(1.05);
}

.campaign-create-btn--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}

.campaign-kpi :deep(.ui-kpi-icon) {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.85rem;
}

.campaign-kpi :deep(.ui-kpi-icon svg) {
    width: 1.1rem;
    height: 1.1rem;
}

.campaign-kpi--total :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-secondary) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-secondary) 30%, transparent);
}
.campaign-kpi--total :deep(.ui-kpi-icon svg) { color: var(--ui-secondary) !important; }

.campaign-kpi--ongoing :deep(.ui-kpi-icon) {
    background: rgba(21, 93, 252, 0.12);
    border-color: rgba(21, 93, 252, 0.22);
}
.campaign-kpi--ongoing :deep(.ui-kpi-icon svg) { color: #155dfc !important; }

.campaign-kpi--scheduled :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-warning) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-warning) 30%, transparent);
}
.campaign-kpi--scheduled :deep(.ui-kpi-icon svg) { color: var(--ui-warning) !important; }

.campaign-kpi--completed :deep(.ui-kpi-icon) {
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    border-color: color-mix(in srgb, var(--ui-success) 30%, transparent);
}
.campaign-kpi--completed :deep(.ui-kpi-icon svg) { color: var(--ui-success) !important; }
</style>

<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import CampaignTable from '@/Components/Tables/CampaignTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiStatCard from '@/Components/UI/UiStatCard.vue';

    const props = defineProps(['rows', 'filters', 'stats']);

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canAdd = computed(() => hasPermission('campaigns.add'));
</script>
