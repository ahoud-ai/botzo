<script setup>
    import { onMounted, ref } from 'vue';
    import axios from 'axios';
    import debounce from 'lodash/debounce';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
    import { BookUser } from 'lucide-vue-next';

    // Read-only list of the organization's contacts (and its branches'), loaded when the tab is opened.
    const props = defineProps({
        organizationUuid: { type: String, required: true },
    });

    const rows = ref([]);
    const meta = ref(null);
    const search = ref('');
    const loading = ref(false);
    const failed = ref(false);
    let requestId = 0;

    const load = async (page = 1) => {
        const current = ++requestId;
        loading.value = true;
        failed.value = false;

        try {
            const { data } = await axios.get(`/admin/organizations/${props.organizationUuid}/contacts`, {
                params: { page, search: search.value || undefined },
            });

            if (current === requestId) {
                rows.value = data.data ?? [];
                meta.value = data.meta ?? null;
            }
        } catch (error) {
            if (current === requestId) {
                failed.value = true;
            }
        } finally {
            if (current === requestId) {
                loading.value = false;
            }
        }
    };

    const searchLater = debounce(() => load(1), 400);
    const clearSearch = () => {
        search.value = '';
        load(1);
    };

    const showWorkspace = () => rows.value.some((row) => row.workspace);

    onMounted(() => load(1));
</script>

<template>
    <div class="space-y-4">
        <div>
            <label for="org-contacts-search" class="ui-form-label mb-1 block">{{ $t('Search contacts') }}</label>
            <div class="ui-table-search">
                <span class="ui-table-search-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
                </span>
                <input id="org-contacts-search" v-model="search" @input="searchLater" type="search" class="ui-table-search-input" :placeholder="$t('Name, phone or email')" maxlength="100">
                <button v-if="search && !loading" type="button" class="ui-table-search-action" @click="clearSearch" :aria-label="$t('Clear search')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
                </button>
            </div>
        </div>

        <div v-if="failed" class="oct-error" role="alert">
            <p>{{ $t('Contacts could not be loaded.') }}</p>
            <button type="button" class="ui-btn-secondary" @click="load(meta?.current_page || 1)">{{ $t('Try again') }}</button>
        </div>

        <UiEmptyState
            v-else-if="!loading && rows.length === 0"
            :title="search ? $t('No contacts match your search.') : $t('This organization has no contacts yet.')"
        >
            <template #icon><BookUser class="h-6 w-6" aria-hidden="true" /></template>
        </UiEmptyState>

        <div v-else class="ui-table-shell" :aria-busy="loading">
            <table class="ui-table">
                <TableHeader>
                    <TableHeaderRow>
                        <TableHeaderRowItem :position="'first'">{{ $t('Name') }}</TableHeaderRowItem>
                        <TableHeaderRowItem>{{ $t('Phone') }}</TableHeaderRowItem>
                        <TableHeaderRowItem class="hidden md:table-cell">{{ $t('Email') }}</TableHeaderRowItem>
                        <TableHeaderRowItem v-if="showWorkspace()" class="hidden lg:table-cell">{{ $t('Workspace') }}</TableHeaderRowItem>
                        <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('AI assistant') }}</TableHeaderRowItem>
                        <TableHeaderRowItem class="hidden lg:table-cell">{{ $t('Added on') }}</TableHeaderRowItem>
                        <TableHeaderRowItem :position="'last'">{{ $t('Last message') }}</TableHeaderRowItem>
                    </TableHeaderRow>
                </TableHeader>
                <TableBody>
                    <template v-if="loading && rows.length === 0">
                        <TableBodyRow v-for="index in 5" :key="`skeleton-${index}`" class="border-b">
                            <TableBodyRowItem :position="'first'"><span class="oct-skeleton w-32"></span></TableBodyRowItem>
                            <TableBodyRowItem><span class="oct-skeleton w-28"></span></TableBodyRowItem>
                            <TableBodyRowItem class="hidden md:table-cell"><span class="oct-skeleton w-36"></span></TableBodyRowItem>
                            <TableBodyRowItem class="hidden sm:table-cell"><span class="oct-skeleton w-14"></span></TableBodyRowItem>
                            <TableBodyRowItem class="hidden lg:table-cell"><span class="oct-skeleton w-24"></span></TableBodyRowItem>
                            <TableBodyRowItem :position="'last'"><span class="oct-skeleton w-24"></span></TableBodyRowItem>
                        </TableBodyRow>
                    </template>
                    <TableBodyRow v-for="(row, index) in rows" v-else :key="row.uuid" :class="[index < rows.length - 1 ? 'border-b' : '', loading ? 'opacity-60' : '']">
                        <TableBodyRowItem :position="'first'" class="[overflow-wrap:anywhere]">{{ row.name || $t('N/A') }}</TableBodyRowItem>
                        <TableBodyRowItem><span dir="ltr" class="tabular-nums">{{ row.phone || $t('N/A') }}</span></TableBodyRowItem>
                        <TableBodyRowItem class="hidden md:table-cell [overflow-wrap:anywhere]">{{ row.email || '—' }}</TableBodyRowItem>
                        <TableBodyRowItem v-if="showWorkspace()" class="hidden lg:table-cell">{{ row.workspace || '—' }}</TableBodyRowItem>
                        <TableBodyRowItem class="hidden sm:table-cell">
                            <span class="oct-chip" :class="row.ai_enabled ? 'ui-chip-success' : 'ui-chip-neutral'">{{ row.ai_enabled ? $t('On') : $t('Off') }}</span>
                        </TableBodyRowItem>
                        <TableBodyRowItem class="hidden lg:table-cell"><span dir="ltr" class="tabular-nums">{{ row.created_at || '—' }}</span></TableBodyRowItem>
                        <TableBodyRowItem :position="'last'"><span dir="ltr" class="tabular-nums">{{ row.last_message_at || $t('No messages') }}</span></TableBodyRowItem>
                    </TableBodyRow>
                </TableBody>
            </table>
        </div>

        <div v-if="meta && meta.total > 0" class="flex flex-wrap items-center justify-between gap-3 text-sm text-[var(--ui-muted)]">
            <span aria-live="polite">{{ $t('Showing {from} to {to} of {total} contacts', { from: meta.from, to: meta.to, total: meta.total }) }}</span>
            <div v-if="meta.last_page > 1" class="ui-pagination-group">
                <button type="button" class="ui-pagination-btn" :disabled="loading || meta.current_page <= 1" :class="{ 'opacity-50': meta.current_page <= 1 }" @click="load(meta.current_page - 1)" :aria-label="$t('Previous page')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="ui-pagination-meta">
                    <div class="ui-pagination-meta-number">{{ meta.current_page }}</div>
                    <div class="ui-pagination-meta-label">{{ $t('of') }} {{ meta.last_page }}</div>
                </div>
                <button type="button" class="ui-pagination-btn" :disabled="loading || meta.current_page >= meta.last_page" :class="{ 'opacity-50': meta.current_page >= meta.last_page }" @click="load(meta.current_page + 1)" :aria-label="$t('Next page')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.oct-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.125rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.oct-skeleton {
    display: inline-block;
    height: 0.75rem;
    border-radius: 999px;
    background: var(--ui-border);
    animation: oct-pulse 1.4s ease-in-out infinite;
}

.oct-error {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--ui-danger) 30%, transparent);
    border-radius: 1rem;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    color: var(--ui-text);
}

@keyframes oct-pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.45; }
}

@media (prefers-reduced-motion: reduce) {
    .oct-skeleton { animation: none; }
}
</style>
