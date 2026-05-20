<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full capitalize">
            <div class="mt-2 flex flex-col gap-4 md:mt-0 md:flex-row md:items-start md:justify-between">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Campaigns') }}</h2>
                    <p class="mb-2 flex items-center text-sm leading-6 text-gray-600 md:mb-6">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('Add campaigns') }}</span>
                    </p>
                </div>
                <div class="flex md:block">
                    <Link v-if="canAdd" href="/campaigns/create" class="inline-flex w-full items-center justify-center rounded-md bg-primary px-3 py-2 text-sm text-white shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 md:w-auto">{{ $t('Create campaign') }}</Link>
                    <button type="submit" v-else disabled class="inline-flex w-full items-center justify-center rounded-md bg-gray-400 px-3 py-2 text-sm text-white shadow-sm cursor-not-allowed focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 md:w-auto">{{ $t('Create campaign') }}</button>
                </div>
            </div>

            <!-- Table Component-->
            <CampaignTable :rows="props.rows" :filters="props.filters"/>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { Link, usePage } from "@inertiajs/vue3";
    import { computed } from 'vue';
    import CampaignTable from '@/Components/Tables/CampaignTable.vue';

    const props = defineProps(['rows', 'filters']);

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

