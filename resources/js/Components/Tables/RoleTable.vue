<script setup>
    import { ref, computed } from 'vue';
    import debounce from 'lodash/debounce';
    import { router, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import { buildI18nLookupCandidates } from '@/Utils/i18nLookup';
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
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        }
    });

    const { t, te } = useI18n();
    const { canManageRoles } = useWorkspaceAccess();
    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();

    const form = useForm({'test': null});

    const deleteAction = (key) => {
        form.delete('/settings/team/roles/' + key);
    }

    const isLastRow = (index) => {
      return index === props.rows.data.length - 1;
    }

    const params = ref({
        search: props.filters?.search,
    });
    
    const isSearching = ref(false);
    const emit = defineEmits(['edit', 'delete']);

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
        router.visit('/settings/team/roles', {
            method: 'get',
            data: params.value,
        })
    }

    const deleteRole = (uuid) => {
        openAlert(uuid);
    }

    const paginationMeta = computed(() => {
        if (!props.rows || !props.rows.meta) {
            return null;
        }
        const meta = props.rows.meta;
        if (meta && 
            typeof meta.current_page !== 'undefined' && 
            typeof meta.last_page !== 'undefined' && 
            typeof meta.total !== 'undefined') {
            return meta;
        }
        return null;
    })
    
    const hasValidPagination = computed(() => {
        return paginationMeta.value !== null && paginationMeta.value.last_page > 1;
    })

    function localizeKnownValue(value) {
        const rawValue = String(value ?? '').trim();

        if (rawValue === '') {
            return '-';
        }

        for (const candidate of buildI18nLookupCandidates(rawValue)) {
            if (te(candidate)) {
                return t(candidate);
            }
        }

        return rawValue;
    }
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
                <TableHeaderRowItem v-if="canManageRoles" :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index" :class="!isLastRow(index) ? 'border-b' : ''">
                <TableBodyRowItem :position="'first'">{{ localizeKnownValue(item.name) }}</TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <span v-if="item.permissions && item.permissions.includes('*')" class="text-gray-500 font-medium">{{ $t('All Permissions') }}</span>
                    <span v-else class="text-gray-500">{{ item.permissions ? item.permissions.length : 0 }} {{ $t('permissions') }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell text-gray-500">{{ item.teams_count || 0 }} {{ $t('members') }}</TableBodyRowItem>
                <TableBodyRowItem v-if="canManageRoles" :position="'last'">
                    <Dropdown v-if="item.organization_id" :align="'right'" class="mt-2">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md text-sm font-medium text-black hover:bg-opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                            <span class="hover-ui-bg-soft hover:rounded-full w-[fit-content] p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                </svg>
                            </span>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem :href="`/settings/team/roles/${item.uuid}`">{{ $t('Edit') }}</DropdownItem>
                                <DropdownItem v-if="!item.teams_count || item.teams_count === 0" as="button" @click="deleteRole(item.uuid)">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                    <span v-if="!item.organization_id" class="text-gray-400 text-xs">{{ $t('System Role') }}</span>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>
    <Pagination v-if="hasValidPagination" class="mt-3" :pagination="paginationMeta"/>

    <!-- Alert Modal Component-->
    <AlertModal 
        v-model="isOpenAlert" 
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')" 
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>

