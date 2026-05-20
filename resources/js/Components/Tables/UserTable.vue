<script setup>
    import { ref } from 'vue';
    import debounce from 'lodash/debounce';
    import { router } from '@inertiajs/vue3';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import AlertModal from '@/Components/AlertModal.vue';
    import { useAlertModal } from '@/Composables/useAlertModal';
    import 'vue3-toastify/dist/index.css';
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

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        },
        type: {
            type: String
        },
        showDeleteBtn: {
            type: Boolean,
            default: true,
        },
        showRole: {
            type: Boolean,
            default: false,
        }
    });

    const { isOpenAlert, openAlert, confirmAlert } = useAlertModal();
    const { t } = useI18n();

    const form = useForm({'test': null});

    const deleteAction = (key) => {
        form.delete(props.type === 'admin' ? '/admin/team/users/' + key : '/admin/users/' + key);
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

    const isLastRow = (index) => {
      return index === props.rows.data.length - 1;
    }

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const statusLabel = (status) => {
        if (status === 1) {
            return t('Active');
        } else if (status === 0) {
            return t('Inactive');
        } else {
            return status;
        }
    }

    const toTranslationKey = (value) => {
        const normalized = String(value ?? '')
            .trim()
            .replace(/[_\-.]+/g, ' ')
            .replace(/\s+/g, ' ');

        if (!normalized) {
            return '';
        }

        return normalized.charAt(0).toUpperCase() + normalized.slice(1).toLowerCase();
    };

    const roleLabel = (role) => {
        const key = toTranslationKey(role);
        if (!key) {
            return role;
        }

        const translated = t(key);
        if (translated === key && role !== key) {
            return role;
        }

        return translated;
    };

    const isSystemOwner = (item) => {
        return props.type === 'admin' && Boolean(item?.is_system_owner);
    };

    const canShowActions = (item) => {
        return Boolean(item);
    };

    const canDeleteItem = (item) => {
        return props.showDeleteBtn && !isSystemOwner(item) && item?.can_delete_account !== false;
    };

    const runSearch = () => {
        const url = window.location.pathname;

        router.visit(url, {
            method: 'get',
            data: params.value,
        })
    }
</script>
<template>
    <div class="ui-table-search">
        <span class="ui-table-search-icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
        </span>
        <input @input="search" v-model="params.search" type="text" class="ui-table-search-input" :placeholder="$t('Search users')">
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
                <TableHeaderRowItem :position="'first'">{{ $t('Name') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Email') }}</TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">{{ $t('Phone') }}</TableHeaderRowItem>
                <TableHeaderRowItem v-if="type === 'admin' || showRole === true" class="hidden sm:table-cell">{{ $t('Role') }}</TableHeaderRowItem>
                <TableHeaderRowItem>
                    <span class="">{{ $t('Status') }}</span>
                </TableHeaderRowItem>
                <TableHeaderRowItem class="hidden sm:table-cell">
                    <span class="block text-end">{{ $t('Last updated') }}</span>
                </TableHeaderRowItem>
                <TableHeaderRowItem :position="'last'"></TableHeaderRowItem>
            </TableHeaderRow>
        </TableHeader>
        <TableBody>
            <TableBodyRow v-for="(item, index) in rows.data" :key="index" :class="!isLastRow(index) ? 'border-b' : ''">
                <TableBodyRowItem :position="'first'" class="capitalize">{{ item.full_name }}</TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">{{ item.email }}</TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">{{ item.phone ?? $t('N/A') }}</TableBodyRowItem>
                <TableBodyRowItem v-if="type === 'admin' || showRole === true" class="hidden sm:table-cell capitalize">
                    <div>
                        <span>{{ roleLabel(item.role) }}</span>
                        <p v-if="isSystemOwner(item)" class="mt-1 text-xs normal-case text-indigo-700">{{ item.system_owner_label || $t('System Owner') }}</p>
                        <p v-if="item.role_hint" class="mt-1 text-xs normal-case text-slate-500">{{ item.role_hint }}</p>
                    </div>
                </TableBodyRowItem>
                <TableBodyRowItem class="capitalize">
                    <span class="py-1 rounded-md text-xs px-3 ui-chip-neutral text-slate-700">{{ statusLabel(item.status) }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem class="hidden sm:table-cell">
                    <span class="block text-end">{{ item.updated_at }}</span>
                </TableBodyRowItem>
                <TableBodyRowItem :position="'last'">
                    <Dropdown v-if="canShowActions(item)" :align="'right'" class="mt-2">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md text-sm font-medium text-black hover:bg-opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                            <span class="hover-ui-bg-soft hover:rounded-full w-[fit-content] p-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M12 16a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2m0-6a2 2 0 0 1 2 2a2 2 0 0 1-2 2a2 2 0 0 1-2-2a2 2 0 0 1 2-2Z"/>
                                </svg>
                            </span>
                        </button>
                        <template #items>
                            <DropdownItemGroup>
                                <DropdownItem :href="type === 'admin' ? '/admin/team/users/' + item.id : '/admin/users/' + item.id">{{ $t('View/edit') }}</DropdownItem>
                                <DropdownItem v-if="canDeleteItem(item)" as="button" @click="openAlert(item.id)">{{ $t('Delete') }}</DropdownItem>
                            </DropdownItemGroup>
                        </template>
                    </Dropdown>
                </TableBodyRowItem>
            </TableBodyRow>
        </TableBody>
    </Table>

    <!-- Alert Modal Component-->
    <AlertModal 
        v-model="isOpenAlert" 
        @confirm="() => confirmAlert(deleteAction)"
        :label = "$t('Delete row')" 
        :description = "$t('Are you sure you want to delete this row? This action can not be undone')"
    />
</template>
  

