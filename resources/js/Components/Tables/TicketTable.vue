<script setup>
    import { Link, router, usePage } from "@inertiajs/vue3";
    import { computed, ref } from "vue";
    import { useI18n } from 'vue-i18n';
    import debounce from 'lodash/debounce';
    import 'vue3-toastify/dist/index.css';
    import Table from '@/Components/Table.vue';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';

    const { t, te } = useI18n();

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
    });

    const user = computed(() => usePage().props.auth.user);

    const ticketUrl = (uuid) => {
        return usePage().props.auth.user.role != 'user' ? '/admin/support/' + uuid : '/support/' + uuid;
    }

    const localizeKnownValue = (value, fallback = '-') => {
        const rawValue = String(value ?? '').trim();

        if (rawValue === '') {
            return fallback;
        }

        const lowerValue = rawValue.toLowerCase();
        const capitalizedValue = lowerValue.charAt(0).toUpperCase() + lowerValue.slice(1);
        const translationKey = [rawValue, lowerValue, capitalizedValue].find((candidate) => te(candidate));

        return translationKey ? t(translationKey) : rawValue;
    };

    const statusChipClass = (status) => {
        switch (String(status ?? '').toLowerCase()) {
            case 'open':
                return 'ui-chip-info';
            case 'pending':
                return 'ui-chip-warning';
            case 'resolved':
                return 'ui-chip-success';
            case 'closed':
                return 'ui-chip-neutral';
            default:
                return 'ui-chip-neutral';
        }
    };

    const priorityChipClass = (priority) => {
        switch (String(priority ?? '').toLowerCase()) {
            case 'critical':
            case 'high':
                return 'ui-chip-danger';
            case 'medium':
                return 'ui-chip-warning';
            case 'low':
                return 'ui-chip-neutral';
            default:
                return 'ui-chip-neutral';
        }
    };

    // Search & status filter
    const params = ref({
        search: props.filters?.search ?? null,
        status: props.filters?.status ?? 'all',
    });

    const statusOptions = computed(() => ([
        { value: 'all', label: t('All statuses') },
        { value: 'open', label: t('open') },
        { value: 'pending', label: t('pending') },
        { value: 'resolved', label: t('resolved') },
        { value: 'closed', label: t('closed') },
    ]));

    const isSearching = ref(false);

    const runSearch = () => {
        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([, value]) => value !== null && value !== 'all' && value !== '')
        );

        router.visit('/support', {
            method: 'get',
            data: filteredParams,
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    };

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 600);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    };

    const applyStatus = (value) => {
        params.value.status = value;
        runSearch();
    };
</script>
<template>
    <div v-if="user.role === 'user'" class="ticket-toolbar">
        <div class="ui-table-search">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search tickets')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>

        <div class="ticket-filter-group">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="ticket-filter-pill"
                :class="{ 'ticket-filter-pill--active': params.status === option.value }"
                @click="applyStatus(option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>

    <Table :rows="rows">
        <TableHeader>
            <TableHeaderRow>
                <TableHeaderRowItem :position="'first'">{{ $t('Ref') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Subject') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Priority') }}</TableHeaderRowItem>
                <TableHeaderRowItem v-if="user.role != 'user'">{{ $t('User') }}</TableHeaderRowItem>
                <TableHeaderRowItem v-if="user.role != 'user'">{{ $t('Assigned to') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Status') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Last updated') }}</TableHeaderRowItem>
                <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index">
                <TableBodyRowItem :position="'first'">
                    <Link :href="ticketUrl(item.uuid)" class="font-bold text-[15px] text-[var(--ui-text)]">
                        {{ item.reference }}
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <Link :href="ticketUrl(item.uuid)">
                        <div class="ticket-subject-pill">
                            {{ item.subject }}
                        </div>
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <Link :href="ticketUrl(item.uuid)">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-bold capitalize" :class="priorityChipClass(item.priority)">{{ localizeKnownValue(item.priority, $t('Not set')) }}</span>
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem v-if="user.role != 'user'" class="hidden sm:table-cell">
                    <Link :href="ticketUrl(item.uuid)">
                        <span class="text-start">{{ item.user.first_name + ' ' + item.user.last_name }}</span>
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem v-if="user.role != 'user'" class="hidden sm:table-cell">
                    <Link :href="ticketUrl(item.uuid)">
                        <span class="text-start">{{ item.agent ? item.agent?.first_name + ' ' + item.agent?.last_name : $t('Not set') }}</span>
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem>
                    <Link :href="ticketUrl(item.uuid)">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-bold capitalize" :class="statusChipClass(item.status)">
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            {{ localizeKnownValue(item.status) }}
                        </span>
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <Link :href="ticketUrl(item.uuid)">
                        <span class="text-[var(--ui-muted)]">{{ item.updated_at }}</span>
                    </Link>
                </TableBodyRowItem>
                <TableBodyRowItem :position="'last'">
                    <div class="flex items-center">
                        <Link :href="ticketUrl(item.uuid)" class="ticket-row-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M9.97 7.47a.75.75 0 0 1 1.06 0l4 4a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 1 1-1.06-1.06L13.44 12L9.97 8.53a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </Link>
                    </div>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>

    <UiEmptyState v-if="rows.data.length === 0" :title="$t('No tickets found')">
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
        </template>
    </UiEmptyState>
</template>

<style scoped>
.ticket-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.ticket-filter-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
}

.ticket-filter-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.9rem;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
    transition: background-color 160ms ease, color 160ms ease, border-color 160ms ease;
}

.ticket-filter-pill:hover {
    color: var(--ui-text);
    background: var(--ui-border);
}

.ticket-filter-pill--active {
    color: #fff;
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.ticket-subject-pill {
    display: inline-block;
    max-width: 20rem;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    padding: 0.35rem 0.7rem;
    border-radius: 0.5rem;
    background: var(--ui-surface-soft);
    border: 1px dashed var(--ui-border);
    font-size: 0.75rem;
    color: var(--ui-text);
}

.ticket-row-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border-radius: 999px;
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.ticket-row-arrow:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}
</style>
