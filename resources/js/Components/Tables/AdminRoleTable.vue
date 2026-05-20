<script setup>
    import { ref, computed } from 'vue';
    import debounce from 'lodash/debounce';
    import { router, useForm } from "@inertiajs/vue3";
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import Table from '@/Components/Table.vue';
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
    import { useAdminPermission } from "@/Composables/useAdminPermission";

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({})
        }
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();
    const { adminCan } = useAdminPermission();

    const form = useForm({ test: null });

    const deleteAction = (key) => {
        form.delete('/admin/team/roles/' + key);
    };

    const isLastRow = (index) => {
      return index === props.rows.data.length - 1;
    };

    const params = ref({
        search: props.filters?.search ?? null,
    });

    const isSearching = ref(false);

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    };

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 400);

    const runSearch = () => {
        router.visit('/admin/team/roles', {
            method: 'get',
            data: params.value,
            preserveState: true,
            onFinish: () => {
                isSearching.value = false;
            },
        });
    };

    const deleteRole = (uuid) => {
        openAlert(uuid);
    };

    const paginationMeta = computed(() => {
        if (!props.rows || !props.rows.meta) {
            return null;
        }

        const meta = props.rows.meta;
        if (
            typeof meta.current_page !== 'undefined' &&
            typeof meta.last_page !== 'undefined' &&
            typeof meta.total !== 'undefined'
        ) {
            return meta;
        }

        return null;
    });

    const hasValidPagination = computed(() => {
        return paginationMeta.value !== null && paginationMeta.value.last_page > 1;
    });

    const canShowActionsColumn = computed(() => {
        return adminCan('roles', 'edit') || adminCan('roles', 'delete');
    });

    const canEditRole = (item) => {
        return adminCan('roles', 'edit') && Boolean(item.can_edit);
    };

    const canDeleteRole = (item) => {
        return adminCan('roles', 'delete') && Boolean(item.can_delete);
    };
</script>

<template>
    <div class="ui-table-search">
        <span class="ui-table-search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search roles')">
        <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
        </button>
        <span v-if="isSearching" class="ui-table-search-action">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
        </span>
    </div>

    <Table :rows="rows">
        <TableHeader>
            <TableHeaderRow>
                <TableHeaderRowItem :position="'first'">{{ $t('Role Name') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Permissions') }}</TableHeaderRowItem>
                <TableHeaderRowItem>{{ $t('Team Members') }}</TableHeaderRowItem>
                <TableHeaderRowItem v-if="canShowActionsColumn" :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="item.uuid ?? index" :class="!isLastRow(index) ? 'border-b' : ''">
                <TableBodyRowItem :position="'first'" class="capitalize">
                    <span>{{ item.name }}</span>
                    <span
                        v-if="item.is_system_role"
                        class="ms-2 inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600"
                    >
                        {{ $t('System Role') }}
                    </span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell text-gray-500">
                    {{ item.permissions_count ?? 0 }} {{ $t('permissions') }}
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell text-gray-500">
                    {{ item.members_count ?? 0 }} {{ $t('members') }}
                </TableBodyRowItem>
                <TableBodyRowItem v-if="canShowActionsColumn" :position="'last'">
                    <Dropdown v-if="canEditRole(item) || canDeleteRole(item)" :align="'right'" class="mt-2">
                        <button type="button" class="inline-flex w-full justify-center rounded-md text-sm font-medium text-black hover:bg-opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                            <span class="hover-ui-bg-soft hover:rounded-full w-[fit-content] p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                </svg>
                            </span>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem v-if="canEditRole(item)" :href="`/admin/team/roles/${item.uuid}`">{{ $t('Edit') }}</DropdownItem>
                                <DropdownItem v-if="canDeleteRole(item)" as="button" @click="deleteRole(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>

    <Pagination v-if="hasValidPagination" class="mt-3" :pagination="paginationMeta"/>

    <AlertModal
        v-model="isOpenAlert"
        @confirm="() => confirmAlert(deleteAction)"
        :label="$t('Delete row')"
        :description="$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

