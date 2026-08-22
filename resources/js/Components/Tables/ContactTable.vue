<script setup>
    import { computed, ref, watchEffect, onMounted, onUpdated } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router, useForm, usePage } from "@inertiajs/vue3";
    import ContactImportModal from '@/Components/ContactImportModal.vue';
    import ExportModal from '@/Components/ExportModal.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import Modal from '@/Components/Modal.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import Pagination from '@/Components/Pagination.vue';
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps(['rows', 'filters', 'type', 'contactGroups']);

    // Helper function to check permissions
    const hasPermission = (permission) => {
        const pageProps = usePage().props;
        const permissions = pageProps?.permissions;
        if (!permissions || !Array.isArray(permissions)) {
            return false;
        }
        return permissions.includes('*') || permissions.includes(permission);
    };

    const canDelete = computed(() => hasPermission('contacts.delete'));
    const canEdit = computed(() => hasPermission('contacts.edit'));
    const canImport = computed(() => hasPermission('contacts.import'));
    const canExport = computed(() => hasPermission('contacts.export'));

    const params = ref({
        id: props.filters?.id,
        search: props.filters?.search,
        page: props.filters?.page
    });
    
    const isOpenModal = ref(false);
    const isExportModalOpen = ref(false);
    const isAssignGroupModalOpen = ref(false);
    const isSearching = ref(false);
    const emit = defineEmits(['callback']);
    const bulkCheckbox = ref(false);
    const selectedCount = ref(0);
    const checkedContacts = ref([]);
    const checkedGroups = ref([]);
    const assignGroupForm = useForm({
        group_uuid: null,
        uuids: [],
    });

    const contactGroupOptions = computed(() => {
        if (!Array.isArray(props.contactGroups)) {
            return [];
        }

        return props.contactGroups.map((group) => ({
            value: group.uuid,
            label: group.name,
        }));
    });

    function getRow(value) {
        params.value.id = value;

        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([_, value]) => value !== null)
        );

        emit('callback', filteredParams);
    }

    const clearSearch = () => {
        params.value.search = null;
        runSearch();
    }

    const search = debounce(() => {
        params.value.page = null;
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        const filteredParams = Object.fromEntries(
            Object.entries(params.value).filter(([_, value]) => value !== null)
        );

        router.visit(props.type === 'contact' ? '/contacts' : '/contact-groups', {
            method: 'get',
            data: filteredParams,
        })
    }

    // Save checked items to local storage
    function saveCheckedItems() {
        if(props.type === 'contact'){
            localStorage.setItem('checkedContacts', JSON.stringify(checkedContacts.value));
        } else {
            localStorage.setItem('checkedGroups', JSON.stringify(checkedGroups.value));
        }
    }

    // Load checked items from local storage
    function loadCheckedItems() {
        if(props.type === 'contact'){
            const savedItems = localStorage.getItem('checkedContacts');
            checkedContacts.value = savedItems ? JSON.parse(savedItems) : [];
        } else {
            const savedItems = localStorage.getItem('checkedGroups');
            checkedGroups.value = savedItems ? JSON.parse(savedItems) : [];
        }
    }

    // Function to update checked items array
    function updateCheckedItems(uuid, isChecked) {
        if(props.type === 'contact'){
            const index = checkedContacts.value.indexOf(uuid);

            if (isChecked && index === -1) {
                checkedContacts.value.push(uuid);
            } else if (!isChecked && index !== -1) {
                checkedContacts.value.splice(index, 1);
            }
        } else {
            const index = checkedGroups.value.indexOf(uuid);

            if (isChecked && index === -1) {
                checkedGroups.value.push(uuid);
            } else if (!isChecked && index !== -1) {
                checkedGroups.value.splice(index, 1);
            }
        }

        saveCheckedItems();
    }

    // Function to toggle individual checkbox
    function toggleCheckbox(contactUuid) {
        const contact = props.rows.data.find(contact => contact.uuid === contactUuid);
        contact.isChecked = !contact.isChecked;
        updateCheckedItems(contactUuid, contact.isChecked);
        updateBulkCheckboxState();
        updateSelectedCount();
    }

    // Function to toggle all checkboxes
    function toggleAllCheckboxes() {
        bulkCheckbox.value = !bulkCheckbox.value;
        props.rows.data.forEach(row => {
            row.isChecked = bulkCheckbox.value;
            updateCheckedItems(row.uuid, bulkCheckbox.value);
        });
        updateSelectedCount();
    }

    // Function to apply checked state from local storage
    function applyCheckedState() {
        props.rows.data.forEach(row => {
            row.isChecked = props.type === 'contact' ? checkedContacts.value.includes(row.uuid) : checkedGroups.value.includes(row.uuid);
        });
        updateBulkCheckboxState();
        updateSelectedCount();
    }

    // Function to update bulk checkbox state
    function updateBulkCheckboxState() {
        bulkCheckbox.value = props.rows.data.length > 0 && props.rows.data.every(row => row.isChecked);
    }

    // Function to update selected count based on checked items array
    function updateSelectedCount() {
        selectedCount.value = props.type === 'contact' ? checkedContacts.value.length : checkedGroups.value.length;
    }

    function deleteItems(value){
        if (props.type === 'contact' && !canDelete.value) {
            return;
        }
        
        const itemsToDelete = props.type === 'contact' ? checkedContacts.value : checkedGroups.value;

        router.visit(props.type === 'contact' ? '/contacts' : '/contact-groups', {
            method: 'delete',
            data: { 'uuids': value === 'all' ? [] : itemsToDelete },
            preserveState: true,
            onSuccess: () => {
                localStorage.removeItem(props.type === 'contact' ? 'checkedContacts' : 'checkedGroups');
                if(props.type === 'contact'){
                    checkedContacts.value = [];
                } else {
                    checkedGroups.value = [];
                }
            }
        })
    }

    function openAssignGroupModal() {
        if (props.type !== 'contact' || selectedCount.value === 0) {
            return;
        }

        assignGroupForm.group_uuid = null;
        assignGroupForm.uuids = [];
        assignGroupForm.clearErrors();
        isAssignGroupModalOpen.value = true;
    }

    function closeAssignGroupModal() {
        isAssignGroupModalOpen.value = false;
        assignGroupForm.reset();
        assignGroupForm.clearErrors();
    }

    function assignSelectedToGroup() {
        if (props.type !== 'contact') {
            return;
        }

        assignGroupForm.uuids = [...checkedContacts.value];

        assignGroupForm.post('/contacts/assign-group', {
            preserveScroll: true,
            onSuccess: () => {
                closeAssignGroupModal();
                localStorage.removeItem('checkedContacts');
                checkedContacts.value = [];
                applyCheckedState();
                updateSelectedCount();
            }
        });
    }

    // Lifecycle hooks
    onMounted(() => {
        loadCheckedItems();
        applyCheckedState();
    });

    onUpdated(() => {
        applyCheckedState();
    });

    watchEffect(() => {
        params.value.page = props.filters?.page;
        applyCheckedState();
    });
</script>
<template>
    <div class="flex h-full min-h-0 flex-col">
    <div class="px-4 pb-2">
        <div class="ui-table-search mt-6 w-full">
            <span class="ui-table-search-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 15l6 6m-11-4a7 7 0 1 1 0-14a7 7 0 0 1 0 14Z"/></svg>
            </span>
            <input 
                @input="search" 
                v-model="params.search" 
                type="text" 
                class="ui-table-search-input" 
                :placeholder="type === 'contact' ? $t('Search name or phone or email') : $t('Search name')"
            >
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="ui-table-search-action">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>
    </div>
    <div class="flex px-4 border-b border-slate-100 dark:border-white/10">
        <Link href="/contacts" class="contact-tab" :class="{ 'contact-tab--active': $page.url.startsWith('/contacts') }">{{ $t('All contacts') }}</Link>
        <Link href="/contact-groups" class="contact-tab" :class="{ 'contact-tab--active': $page.url.startsWith('/contact-groups') }">{{ $t('Groups') }}</Link>
    </div>
    <div class="flex justify-between items-center px-4 py-2 border-b border-slate-100 dark:border-white/10">
        <div class="flex items-center gap-x-2">
            <label @click="toggleAllCheckboxes($event)" class="contact-checkbox" :class="{ 'contact-checkbox--checked': bulkCheckbox }">
                <svg v-if="bulkCheckbox" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                </svg>
            </label>
            <label @click="toggleAllCheckboxes($event)" class="cursor-pointer text-[13px] font-medium text-slate-600 dark:text-slate-400">
                <span v-if="selectedCount == 0">{{ $t('Select all') }} ({{ selectedCount }})</span>
                <span v-else-if="selectedCount > 0">{{ selectedCount }} {{ $t('selected') }}</span>
            </label>
        </div>
        <div>
            <div class="flex justify-end">
                <Dropdown v-if="type === 'contact' && (canImport || canExport || canDelete || canEdit)" :align="'right'">
                    <button type="submit" class="contact-toolbar-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16"><path fill="currentColor" d="M8 2.5a1.22 1.22 0 0 1 1.25 1.17A1.21 1.21 0 0 1 8 4.84a1.21 1.21 0 0 1-1.25-1.17A1.22 1.22 0 0 1 8 2.5m0 8.66a1.17 1.17 0 1 1-1.25 1.17A1.21 1.21 0 0 1 8 11.16m0-4.33a1.17 1.17 0 1 1 0 2.34a1.17 1.17 0 1 1 0-2.34"/></svg>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem v-if="type === 'contact' && canImport" as="button" @click="isOpenModal = true">{{ $t('Import rows') }}</DropdownItem>
                            <DropdownItem v-if="type === 'contact' && canExport" as="button" @click="isExportModalOpen = true">{{ $t('Export') }}</DropdownItem>
                            <DropdownItem v-if="type === 'contact' && canEdit && selectedCount > 0" as="button" @click="openAssignGroupModal()">{{ $t('Add selected to group') }}</DropdownItem>
                            <DropdownItem v-if="type === 'contact' && canDelete && selectedCount > 0" as="button" @click="deleteItems()">{{ $t('Delete selected') }}</DropdownItem>
                            <DropdownItem v-if="type === 'contact' && canDelete" as="button" @click="deleteItems('all')">{{ $t('Delete all') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>
            </div>
        </div>
    </div>
    <div class="flex-grow min-h-0 overflow-y-auto" ref="scrollContainer">
            <div v-if="type === 'contact'" @click="getRow(contact.uuid)" class="contact-row" :class="{ 'contact-row--checked': contact.isChecked }" v-for="(contact, index) in rows.data" :key="index">
            <div class="shrink-0 pt-1">
                <label @click.stop="toggleCheckbox(contact.uuid)" class="contact-checkbox" :class="{ 'contact-checkbox--checked': contact.isChecked }">
                    <svg v-if="contact.isChecked" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                </label>
            </div>
            <div class="shrink-0">
                <img v-if="contact.avatar" class="rounded-full h-11 w-11 object-cover" :src="contact.avatar" alt="">
                <div v-else class="rounded-full bg-secondary/10 text-secondary flex justify-center items-center h-11 w-11 font-semibold">{{ contact.first_name?.substring(0, 1) }}</div>
            </div>
            <div class="min-w-0 flex-1">
                <h3 class="truncate text-[15px] font-semibold text-[var(--ui-text)]">{{ contact?.full_name }}</h3>
                <p class="text-slate-500 dark:text-slate-400 text-[13px] truncate">{{ contact.formatted_phone_number }}</p>
            </div>
            <div class="shrink-0 pt-1">
                <svg v-if="contact.is_favorite" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="#f5b400" d="M9.153 5.408C10.42 3.136 11.053 2 12 2c.947 0 1.58 1.136 2.847 3.408l.328.588c.36.646.54.969.82 1.182c.28.213.63.292 1.33.45l.636.144c2.46.557 3.689.835 3.982 1.776c.292.94-.546 1.921-2.223 3.882l-.434.507c-.476.557-.715.836-.822 1.18c-.107.345-.071.717.001 1.46l.066.677c.253 2.617.38 3.925-.386 4.506c-.766.582-1.918.051-4.22-1.009l-.597-.274c-.654-.302-.981-.452-1.328-.452c-.347 0-.674.15-1.328.452l-.596.274c-2.303 1.06-3.455 1.59-4.22 1.01c-.767-.582-.64-1.89-.387-4.507l.066-.676c.072-.744.108-1.116 0-1.46c-.106-.345-.345-.624-.821-1.18l-.434-.508c-1.677-1.96-2.515-2.941-2.223-3.882c.293-.941 1.523-1.22 3.983-1.776l.636-.144c.699-.158 1.048-.237 1.329-.45c.28-.213.46-.536.82-1.182z"/></svg>
            </div>
        </div>
        <div v-else-if="type === 'group'" @click="getRow(row.uuid)" class="contact-row" :class="{ 'contact-row--checked': row.isChecked }" v-for="(row, key) in rows.data" :key="key">
            <div class="shrink-0 pt-1">
                <label @click.stop="toggleCheckbox(row.uuid)" class="contact-checkbox" :class="{ 'contact-checkbox--checked': row.isChecked }">
                    <svg v-if="row.isChecked" class="w-3 h-3 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                </label>
            </div>
            <div class="shrink-0">
                <div class="rounded-full bg-secondary/10 text-secondary flex justify-center items-center h-11 w-11 font-semibold capitalize">{{ row.name.substring(0, 1) }}</div>
            </div>
            <div class="min-w-0 flex-1 flex items-center">
                <h3 class="truncate text-[15px] font-semibold text-[var(--ui-text)]">{{ row.name }}</h3>
            </div>
        </div>
    </div>
    <div class="border-t border-slate-100 dark:border-white/10 px-4 py-3">
        <Pagination :pagination="rows.meta"/>
    </div>
    <ContactImportModal :type="type" v-model:modelValue="isOpenModal"/>
    <ExportModal :type="type" v-model:modelValue="isExportModalOpen"/>
    <Modal :isOpen="isAssignGroupModalOpen" :label="$t('Add selected to group')" :closeBtn="true" @close="closeAssignGroupModal">
        <div class="pt-4">
            <FormSelect
                v-model="assignGroupForm.group_uuid"
                :name="$t('Group')"
                :options="contactGroupOptions"
                :placeholder="$t('Select group')"
                :error="assignGroupForm.errors.group_uuid"
            />

            <div v-if="contactGroupOptions.length === 0" class="text-sm text-slate-500 dark:text-slate-400 mt-2">
                {{ $t('No groups available. Please create a group first.') }}
            </div>
            <div v-if="assignGroupForm.errors.uuids" class="ui-form-error mt-2">
                {{ assignGroupForm.errors.uuids }}
            </div>

            <div class="mt-5 flex items-center justify-end gap-x-3">
                <button type="button" class="contact-modal-btn contact-modal-btn--ghost" @click="closeAssignGroupModal">
                    {{ $t('Cancel') }}
                </button>
                <button
                    type="button"
                    class="contact-modal-btn contact-modal-btn--solid"
                    :disabled="assignGroupForm.processing || contactGroupOptions.length === 0"
                    @click="assignSelectedToGroup"
                >
                    {{ $t('Add to group') }}
                </button>
            </div>
        </div>
    </Modal>
    </div>
</template>

<style scoped>
.contact-tab {
    flex: 1;
    text-align: center;
    padding: 0.75rem 0.5rem 0.65rem;
    font-size: 0.86rem;
    font-weight: 500;
    color: var(--ui-muted);
    border-bottom: 2px solid transparent;
    transition: color 160ms ease, border-color 160ms ease;
}

.contact-tab:hover {
    color: var(--ui-text);
}

.contact-tab--active {
    color: var(--ui-text);
    font-weight: 600;
    border-bottom-color: var(--ui-secondary);
}

.contact-checkbox {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.15rem;
    height: 1.15rem;
    border-radius: 0.35rem;
    border: 1.5px solid var(--ui-border-strong);
    cursor: pointer;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.contact-checkbox--checked {
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

.contact-toolbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 999px;
    color: var(--ui-muted);
    background: transparent;
    cursor: pointer;
    transition: background-color 160ms ease, color 160ms ease;
}

.contact-toolbar-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.contact-row {
    display: flex;
    min-width: 0;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid var(--ui-border);
    transition: background-color 120ms ease;
}

.contact-row:hover {
    background: var(--ui-surface-soft);
}

.contact-row--checked {
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
}

.contact-modal-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.65rem;
    padding: 0.5rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: background-color 160ms ease, filter 160ms ease;
}

.contact-modal-btn--ghost {
    color: var(--ui-text);
    background: var(--ui-surface-soft);
    border: 1px solid var(--ui-border);
}

.contact-modal-btn--ghost:hover {
    background: var(--ui-border);
}

.contact-modal-btn--solid {
    color: #fff;
    background: var(--ui-secondary);
    border: 1px solid var(--ui-secondary);
}

.contact-modal-btn--solid:hover {
    filter: brightness(1.05);
}

.contact-modal-btn--solid:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    filter: none;
}
</style>
  
