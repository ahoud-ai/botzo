<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full capitalize">
            <div class="flex justify-between mt-8 md:mt-0">
                <div>
                    <h2 class="text-xl mb-1">{{ $t('Message templates') }}</h2>
                    <p class="mb-6 flex items-center text-sm leading-6 text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                        <span class="ms-1 mt-1">{{ $t('Add template') }}</span>
                    </p>
                </div>
                <div class="gap-x-2 flex items-center">
                    <button type="button" v-if="canSync" @click="syncTemplates" class="rounded-md px-3 py-2 text-sm text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 capitalize" :class="!isSyncActive ? 'bg-indigo-600 hover:bg-indigo-500 shadow-sm' : 'bg-gray-200'" :disabled="isSyncActive">
                        <span v-if="!isSyncActive">{{ $t('Sync templates') }}</span>
                        <svg v-else class="text-slate-600 animate-spin" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M18.43 4.25a.76.76 0 0 0-.75.75v2.43l-.84-.84a7.24 7.24 0 0 0-12 2.78a.74.74 0 0 0 .46 1a.73.73 0 0 0 .25 0a.76.76 0 0 0 .71-.51a5.63 5.63 0 0 1 1.37-2.2a5.76 5.76 0 0 1 8.13 0l.84.84h-2.41a.75.75 0 0 0 0 1.5h4.24a.74.74 0 0 0 .75-.75V5a.75.75 0 0 0-.75-.75Zm.25 9.43a.76.76 0 0 0-1 .47a5.63 5.63 0 0 1-1.37 2.2a5.76 5.76 0 0 1-8.13 0l-.84-.84h2.47a.75.75 0 0 0 0-1.5H5.57a.74.74 0 0 0-.75.75V19a.75.75 0 0 0 1.5 0v-2.43l.84.84a7.24 7.24 0 0 0 12-2.78a.74.74 0 0 0-.48-.95Z"/></svg>
                    </button>
                    <Link v-if="canAdd" href="/templates/create" class="md:block hidden rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Create template') }}</Link>
                    <button type="submit" v-else-if="!canAdd" disabled class="md:block hidden rounded-md bg-gray-400 px-3 py-2 text-sm text-white shadow-sm cursor-not-allowed focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Create template') }}</button>
                </div>
            </div>

            <!-- Table Component-->
            <TemplateTable :rows="props.rows"/>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { router, usePage } from '@inertiajs/vue3';
    import { Link } from "@inertiajs/vue3";
    import TemplateTable from '@/Components/Tables/TemplateTable.vue';
    import axios from 'axios';
    import { ref, computed } from 'vue';

    const props = defineProps({ rows: Object });
    const isSyncActive = ref(false);

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canSync = computed(() => hasPermission('message_templates.sync'));
    const canAdd = computed(() => hasPermission('message_templates.add'));

    const syncTemplates = () => {
        if (!canSync.value) {
            return;
        }
        isSyncActive.value = true;
        axios.get('/templates/sync')
        .then(function (response) {
            router.reload();
            setTimeout(() => {
                isSyncActive.value = false;
            }, 1500);
        })
        .catch(function (error) {
            isSyncActive.value = false;
        });
    }
</script>

