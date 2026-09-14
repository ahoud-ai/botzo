<script setup>
    import 'vue3-toastify/dist/index.css';
    import { ref, computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import debounce from 'lodash/debounce';
    import { router } from '@inertiajs/vue3';
    import Modal from '@/Components/Modal.vue';
    import Table from '@/Components/Table.vue';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const { t } = useI18n();

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
        uuid: {
            type: String
        }
    });

    const params = ref({
        search: props.filters?.search ?? null,
        status: props.filters?.status ?? 'all',
    });

    const statusOptions = computed(() => ([
        { value: 'all', label: t('All statuses') },
        { value: 'pending', label: t('pending') },
        { value: 'sent', label: t('sent') },
        { value: 'delivered', label: t('delivered') },
        { value: 'read', label: t('read') },
        { value: 'failed', label: t('failed') },
    ]));

    const logs = ref(null);
    const messageStatus = ref(null);
    const isOpenModal = ref(false);
    const isSearching = ref(false);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 600);

    const runSearch = () => {
        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([, value]) => value !== null && value !== 'all' && value !== '')
        );

        router.visit('/campaigns/' + props.uuid, {
            method: 'get',
            data: filteredParams,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    }

    const applyStatus = (value) => {
        params.value.status = value;
        runSearch();
    };

    const openModal = (status, value) => {
        messageStatus.value = status;
        logs.value = value;
        isOpenModal.value = true;
    }

    const getStatus = (metadata) => {
        return JSON.parse(metadata).status;
    }

    const getErrorDetails = (metadata) => {
        return JSON.parse(metadata);
    }

    // Mirrors the exact same "effective status" the table already displayed
    // (chat status when the send succeeded, otherwise the raw log status).
    const effectiveStatus = (item) => (item.status === 'success' ? (item.chat?.status ?? item.status) : item.status);

    // WhatsApp's own real status colors: gray ticks for sent/delivered,
    // the official WhatsApp "read" blue (#53bdeb) for read.
    const statusMeta = (item) => {
        switch (effectiveStatus(item)) {
            case 'read':
                return { color: '#53bdeb', ticks: 2 };
            case 'delivered':
                return { color: 'var(--ui-muted)', ticks: 2 };
            case 'accepted':
            case 'sent':
                return { color: 'var(--ui-muted)', ticks: 1 };
            case 'failed':
                return { color: 'var(--ui-danger)', ticks: 0, failed: true };
            default:
                // pending / ongoing / retrying
                return { color: 'var(--ui-warning)', ticks: 0, pending: true };
        }
    };

    const statusLabel = (item) => t(String(effectiveStatus(item)).toLowerCase());
</script>
<template>
    <div class="log-toolbar">
        <div class="ui-table-search">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search recipients')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>

        <div class="log-filter-group">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="log-filter-pill"
                :class="{ 'log-filter-pill--active': params.status === option.value }"
                @click="applyStatus(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>

    <Table :rows="rows">
        <TableHeader>
            <TableHeaderRow>
                <TableHeaderRowItem :position="'first'" class="hidden sm:table-cell">{{ $t('Contact') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Phone') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Last updated') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Retries') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index">
                <TableBodyRowItem :position="'first'" class="hidden sm:table-cell font-bold text-[var(--ui-text)]">{{ item.contact.full_name }}</TableBodyRowItem>
                <TableBodyRowItem>
                    <span dir="ltr" class="log-phone">{{ item.contact.phone }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <span class="text-[var(--ui-muted)]">{{ item.status === 'success' ? (item.chat?.created_at ?? item.created_at) : item.created_at }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <span class="text-[var(--ui-muted)]">{{ item.retry_count }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <span class="log-status-chip" :style="{ '--chip-color': statusMeta(item).color }">
                        <svg v-if="statusMeta(item).ticks === 2" width="16" height="11" viewBox="0 0 16 11" fill="none"><path d="M11.1.7 4.5 7.3 1.9 4.7.5 6.1 4.5 10.1 12.5 2.1z" fill="currentColor"/><path d="M15.1.7 8.5 7.3 7.6 6.4 6.2 7.8 8.5 10.1 16.5 2.1z" fill="currentColor"/></svg>
                        <svg v-else-if="statusMeta(item).ticks === 1" width="13" height="11" viewBox="0 0 13 11" fill="none"><path d="M11.1.7 4.5 7.3 1.9 4.7.5 6.1 4.5 10.1 12.5 2.1z" fill="currentColor"/></svg>
                        <svg v-else-if="statusMeta(item).failed" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/></svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
                        {{ statusLabel(item) }}
                    </span>
                </TableBodyRowItem>
                <TableBodyRowItem :position="'last'">
                    <button type="button" class="log-more-btn" @click="openModal(item.status, item.status === 'success' ? item.chat?.logs : item.metadata)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"><g fill="currentColor"><path d="M11 10.98a1 1 0 1 1 2 0v6a1 1 0 1 1-2 0zm1-4.929a1 1 0 1 0 0 2a1 1 0 0 0 0-2"/><path fill-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10s10-4.477 10-10S17.523 2 12 2M4 12a8 8 0 1 0 16 0a8 8 0 0 0-16 0" clip-rule="evenodd"/></g></svg>
                        {{ $t('More info') }}
                    </button>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>

    <UiEmptyState v-if="rows.data.length === 0" :title="$t('No recipients found')">
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm12 10v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </template>
    </UiEmptyState>

    <Modal :label="$t('Message info')" :isOpen="isOpenModal" @close="isOpenModal = false">
        <div class="max-w-md w-full space-y-8">
            <div class="mt-8 space-y-2">
                <div v-if="messageStatus === 'success'" v-for="(log, index) in logs" :key="index" class="log-modal-row">
                    <div class="log-modal-row-status">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m1.75 9.75l2.5 2.5m3.5-4l2.5-2.5m-4.5 4l2.5 2.5l6-6.5"/></svg>
                        <span class="capitalize">{{ $t(getStatus(log.metadata)) }}</span>
                    </div>
                    <div class="log-modal-row-time">{{ log.created_at }}</div>
                </div>
                <div v-else-if="messageStatus === 'failed'" class="log-modal-error">
                    <div class="log-modal-error-msg">{{ $t('Error:') }} {{ getErrorDetails(logs).data.error.message }}</div>
                    <div v-if="getErrorDetails(logs).data?.error?.error_data?.details" class="log-modal-error-detail">{{ getErrorDetails(logs).data?.error?.error_data?.details }}</div>
                </div>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <div class="mt-2 w-full">
                <button type="button" @click="isOpenModal = false" class="log-modal-close">{{ $t('Close') }}</button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.log-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.log-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.log-filter-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.9rem;
    font-size: 0.78rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.log-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
}

.log-filter-pill--active {
    color: #fff;
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.log-phone {
    unicode-bidi: isolate;
    color: var(--ui-text);
    font-variant-numeric: tabular-nums;
}

.log-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border-radius: 999px;
    padding: 0.32rem 0.7rem;
    font-size: 0.76rem;
    font-weight: 700;
    text-transform: capitalize;
    background: color-mix(in srgb, var(--chip-color) 15%, transparent);
    color: var(--chip-color);
}

.log-more-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    color: var(--ui-secondary);
    font-size: 0.8rem;
    font-weight: 700;
    background: transparent;
    border: none;
    cursor: pointer;
}

.log-more-btn:hover {
    text-decoration: underline;
}

.log-modal-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.85rem;
    border-bottom: 1px solid var(--ui-border);
    padding-bottom: 0.6rem;
}

.log-modal-row-status {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--ui-text);
    font-weight: 700;
}

.log-modal-row-time {
    color: var(--ui-muted);
    font-size: 0.78rem;
}

.log-modal-error-msg {
    font-size: 0.85rem;
    margin-bottom: 0.7rem;
    background: color-mix(in srgb, var(--ui-danger) 14%, transparent);
    color: var(--ui-danger);
    padding: 0.6rem 0.8rem;
    border-radius: 0.6rem;
}

.log-modal-error-detail {
    font-size: 0.82rem;
    color: var(--ui-muted);
}

.log-modal-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.55rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}

.log-modal-close:hover {
    background: var(--ui-border);
}
</style>
