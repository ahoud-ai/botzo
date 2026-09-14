<script setup>
    import { ref, computed } from 'vue';
    import debounce from 'lodash/debounce';
    import { router, usePage } from '@inertiajs/vue3';
    import { useForm } from "@inertiajs/vue3";
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import 'vue3-toastify/dist/index.css';
    import TableHeader from '@/Components/TableHeader.vue';
    import TableHeaderRow from '@/Components/TableHeaderRow.vue';
    import TableHeaderRowItem from '@/Components/TableHeaderRowItem.vue';
    import TableBody from '@/Components/TableBody.vue';
    import TableBodyRow from '@/Components/TableBodyRow.vue';
    import TableBodyRowItem from '@/Components/TableBodyRowItem.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import Pagination from '@/Components/Pagination.vue';
    import UiEmptyState from '@/Components/UI/UiEmptyState.vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        }
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canAdd = computed(() => hasPermission('automations.add'));
    const canEdit = computed(() => hasPermission('automations.edit'));
    const canDelete = computed(() => hasPermission('automations.delete'));

    const form = useForm({'test': null});

    const deleteAction = (key) => {
        if (!canDelete.value) {
            return;
        }
        form.delete('/automation/basic/' + key);
    }

    const params = ref({
        search: props.filters.search,
    });

    const isSearching = ref(false);
    const emit = defineEmits(['delete']);

    function deleteItem(id) {
        emit('delete', id);
    }

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        router.visit('/automation/basic', {
            method: 'get',
            data: params.value,
        })
    }

    const capitalizeString = (str) => {
        // Check if the string is empty or null
        if (!str) return '';

        // Capitalize the first character and concatenate it with the rest of the string
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    const responseTypeIcon = {
        text: '✎',
        image: '🖼',
        audio: '🔊',
        template: '📄',
    };

    const parseResponseType = (item) => {
        try {
            return JSON.parse(item.metadata)?.type ?? null;
        } catch {
            return null;
        }
    };
</script>
<template>
    <div class="ui-table-search">
        <span class="ui-table-search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search by name or trigger text')">
        <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
        </button>
        <span v-if="isSearching" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
        </span>
    </div>

    <div v-if="rows.data.length > 0" class="ui-table-shell">
        <table class="ui-table">
            <TableHeader>
                <TableHeaderRow>
                    <TableHeaderRowItem :position="'first'">{{ $t('Name') }}</TableHeaderRowItem>
                    <TableHeaderRowItem>{{ $t('Trigger text') }}</TableHeaderRowItem>
                    <TableHeaderRowItem>{{ $t('Match criteria') }}</TableHeaderRowItem>
                    <TableHeaderRowItem>{{ $t('Response type') }}</TableHeaderRowItem>
                    <TableHeaderRowItem>{{ $t('Last updated') }}</TableHeaderRowItem>
                    <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
                </TableHeaderRow>
            </TableHeader>
            <TableBody>
                <TableBodyRow v-for="(item, index) in rows.data" :key="index">
                    <TableBodyRowItem :position="'first'" class="capitalize">{{ item.name }}</TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">
                        <div class="automation-trigger-chip">
                            {{ item.trigger }}
                        </div>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">{{ $t(capitalizeString(item.match_criteria)) }}</TableBodyRowItem>
                    <TableBodyRowItem>
                        <span class="ui-chip-neutral automation-type-chip">
                            <span aria-hidden="true">{{ responseTypeIcon[parseResponseType(item)] ?? '•' }}</span>
                            {{ parseResponseType(item) ? $t(parseResponseType(item)) : '-' }}
                        </span>
                    </TableBodyRowItem>
                    <TableBodyRowItem class="hidden sm:table-cell">{{ item.updated_at }}</TableBodyRowItem>
                    <TableBodyRowItem :position="'last'">
                        <Dropdown v-if="canEdit || canDelete" :align="'right'" class="mt-2">
                            <button type="submit" class="inline-flex w-full justify-center rounded-md text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                                <span class="hover-ui-bg-soft hover:rounded-full w-[fit-content] p-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                        <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                    </svg>
                                </span>
                            </button>
                            <template #items>
                                <DropdownItemGroup>
                                    <DropdownItem v-if="canEdit" :href="'/automation/basic/' + item.uuid + '/edit'">{{ $t('Edit') }}</DropdownItem>
                                    <DropdownItem v-if="canDelete" as="button" @click="openAlert(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                                </DropdownItemGroup>
                            </template>
                        </Dropdown>
                    </TableBodyRowItem>
                </TableBodyRow>
            </TableBody>
        </table>
    </div>

    <UiEmptyState v-else :title="$t('No canned replies found')" :description="$t('Create your first automated reply to respond to customers instantly.')">
        <template #icon>
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15a3 3 0 1 0 0-6a3 3 0 0 0 0 6"/><path d="m19.622 10.395l-1.097-2.65L20 6l-2-2l-1.735 1.483l-2.707-1.113L12.935 2h-1.954l-.632 2.401l-2.645 1.115L6 4L4 6l1.453 1.789l-1.08 2.657L2 11v2l2.401.656L5.516 16.3L4 18l2 2l1.791-1.46l2.606 1.072L11 22h2l.604-2.387l2.651-1.098C16.697 18.832 18 20 18 20l2-2l-1.484-1.75l1.098-2.652l2.386-.62V11z"/></g></svg>
        </template>
        <template #actions>
            <Link v-if="canAdd" href="/automation/basic/create" class="automation-create-btn">{{ $t('Create') }}</Link>
        </template>
    </UiEmptyState>

    <div v-if="rows.data.length > 0" class="px-4 pb-4">
        <Pagination class="mt-3" :pagination="rows.meta"/>
    </div>

    <!-- Alert Modal Component-->
    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')"
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

<style scoped>
.automation-trigger-chip {
    display: inline-block;
    max-width: 12rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding: 0.25rem 0.55rem;
    border: 1px dashed var(--ui-border-strong);
    background: var(--ui-surface-soft);
    border-radius: 0.5rem;
    font-size: 0.75rem;
    color: var(--ui-text);
}

.automation-type-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.automation-create-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.7rem;
    padding: 0.6rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.automation-create-btn:hover {
    filter: brightness(1.05);
}
</style>
