<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Organizations')" :subtitle="$t('Manage organization accounts')">
                <template #actions>
                    <Link
                        v-if="adminCan('organizations', 'create')"
                        href="/admin/organizations/create"
                        class="rounded-xl bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-500"
                    >
                        {{ $t('Add organization') }}
                    </Link>
                </template>
            </UiPageHeader>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <UiStatCard class="ui-kpi--compact" :title="$t('Main organizations')" :value="props.summary?.main_organizations ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M21 21H3a1 1 0 0 1 0-2h1V8a1 1 0 0 1 1-1h4V4a1 1 0 0 1 .684-.949l4-1.333A1 1 0 0 1 15 2.667V7h4a1 1 0 0 1 1 1v11h1a1 1 0 0 1 0 2M10 19h4V4.054l-2 .667l-2 .666zm5 0h3V9h-3zm-9 0h3V9H6z"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Branches')" :value="props.summary?.branches ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M10 3H6a2 2 0 0 0-2 2v4h2V5h4zm8 0h-4v2h4v4h2V5a2 2 0 0 0-2-2M6 15H4v4a2 2 0 0 0 2 2h4v-2H6zm12 4h-4v2h4a2 2 0 0 0 2-2v-4h-2zm-2-8a4 4 0 1 0-8 0a4 4 0 0 0 8 0m-10 0a6 6 0 1 1 12 0a6 6 0 0 1-12 0"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Active subscriptions')" :value="props.summary?.active_subscriptions ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M7 2h10a2 2 0 0 1 2 2v16l-7-3l-7 3V4a2 2 0 0 1 2-2m0 2v12.97l5-2.14l5 2.14V4zm5 2a3 3 0 1 1 0 6a3 3 0 0 1 0-6"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Trial subscriptions')" :value="props.summary?.trial_subscriptions ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M12 20a8 8 0 1 0-8-8a8 8 0 0 0 8 8m0-18A10 10 0 1 1 2 12A10 10 0 0 1 12 2m1 5v4.586l2.707 2.707l-1.414 1.414l-3.293-3.293V7z"/></svg>
                    </template>
                </UiStatCard>
                <UiStatCard class="ui-kpi--compact" :title="$t('Expiring within 7 days')" :value="props.summary?.expiring_soon ?? 0">
                    <template #icon>
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2m0 15H5V10h14zm-3.5-6.5L14 11l-3 3l-1.5-1.5L8 14l3 3l4.5-4.5z"/></svg>
                    </template>
                </UiStatCard>
            </div>

            <div class="mt-6 flex flex-wrap gap-2 rounded-[1rem] border border-slate-200 bg-white px-4 py-3 shadow-sm shadow-slate-200/50">
                <button
                    v-for="option in filterOptions"
                    :key="option.value"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm transition"
                    :class="activeFilter === option.value ? 'bg-primary text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                    @click="applyOrganizationType(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="mt-6">
                <OrganizationTable :rows="props.rows" :filters="props.filters" :plans="props.plans" :type="'user'" :variant="'directory'"/>
            </div>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link, router } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import OrganizationTable from '@/Components/Tables/OrganizationTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiStatCard from '@/Components/UI/UiStatCard.vue';
    import { useI18n } from 'vue-i18n';
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({ 
        allowCreate: {
            type: Boolean,
            default: true
        }, 
        title: String, 
        rows: Object, 
        summary: Object,
        filters: Object,
        plans: {
            type: Array,
            default: () => [],
        },
    });

    const { t } = useI18n();
    const { adminCan } = useAdminPermission();

    const filterOptions = computed(() => ([
        { value: 'main', label: t('Main organizations') },
        { value: 'branch', label: t('Branches only') },
        { value: 'all', label: t('Show all') },
    ]));

    const activeFilter = computed(() => props.filters?.organization_type ?? 'main');

    const applyOrganizationType = (value) => {
        router.visit(window.location.pathname, {
            method: 'get',
            data: {
                search: props.filters?.search ?? null,
                subscription_status: props.filters?.subscription_status ?? null,
                plan_uuid: props.filters?.plan_uuid ?? null,
                branches_scope: props.filters?.branches_scope ?? null,
                organization_type: value,
            },
            preserveScroll: true,
            preserveState: true,
        });
    };
</script>

