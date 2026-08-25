<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Message templates')">
                <template #title>
                    <div class="flex items-baseline gap-x-2">
                        <span>{{ $t('Message templates') }}</span>
                        <span class="templates-count-pill">{{ props.rows?.meta?.total ?? props.rows?.data?.length ?? 0 }}</span>
                    </div>
                </template>
                <template #actions>
                    <UiActionBar :stacked-on-mobile="false">
                        <button
                            v-if="canSync"
                            type="button"
                            class="template-action-btn template-action-btn--ghost"
                            :disabled="isSyncActive"
                            @click="syncTemplates"
                        >
                            <svg v-if="!isSyncActive" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M18.43 4.25a.76.76 0 0 0-.75.75v2.43l-.84-.84a7.24 7.24 0 0 0-12 2.78a.74.74 0 0 0 .46 1a.73.73 0 0 0 .25 0a.76.76 0 0 0 .71-.51a5.63 5.63 0 0 1 1.37-2.2a5.76 5.76 0 0 1 8.13 0l.84.84h-2.41a.75.75 0 0 0 0 1.5h4.24a.74.74 0 0 0 .75-.75V5a.75.75 0 0 0-.75-.75Zm.25 9.43a.76.76 0 0 0-1 .47a5.63 5.63 0 0 1-1.37 2.2a5.76 5.76 0 0 1-8.13 0l-.84-.84h2.47a.75.75 0 0 0 0-1.5H5.57a.74.74 0 0 0-.75.75V19a.75.75 0 0 0 1.5 0v-2.43l.84.84a7.24 7.24 0 0 0 12-2.78a.74.74 0 0 0-.48-.95Z"/></svg>
                            <svg v-else class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".3"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                            <span>{{ $t('Sync templates') }}</span>
                        </button>
                        <Link v-if="canAdd" href="/templates/create" class="template-action-btn template-action-btn--solid">
                            {{ $t('Create template') }}
                        </Link>
                        <button v-else type="button" disabled class="template-action-btn template-action-btn--disabled">
                            {{ $t('Create template') }}
                        </button>
                    </UiActionBar>
                </template>
            </UiPageHeader>

            <!-- Table Component-->
            <TemplateTable :rows="props.rows" :filters="props.filters" :languages="props.languages"/>
        </div>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { router, usePage } from '@inertiajs/vue3';
    import { Link } from "@inertiajs/vue3";
    import TemplateTable from '@/Components/Tables/TemplateTable.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiActionBar from '@/Components/UI/UiActionBar.vue';
    import axios from 'axios';
    import { ref, computed } from 'vue';

    const props = defineProps({ rows: Object, filters: Object, languages: Array });
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

<style scoped>
.templates-count-pill {
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

.template-action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.template-action-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
}

.template-action-btn--solid:hover {
    filter: brightness(1.05);
}

.template-action-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border-color: var(--ui-border) !important;
}

.template-action-btn--ghost:hover:not(:disabled) {
    background: var(--ui-border);
}

.template-action-btn--ghost:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.template-action-btn--disabled {
    background: var(--ui-border-strong);
    color: var(--ui-muted);
    cursor: not-allowed;
}
</style>
