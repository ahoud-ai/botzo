<script setup>
    import axios from 'axios';
    import { ref, watch, onMounted, onBeforeUnmount, computed } from 'vue';
    import debounce from 'lodash/debounce';
    import { Link, router, usePage } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import Pagination from '@/Components/Pagination.vue';
    import TicketStatusToggle from '@/Components/TicketStatusToggle.vue';
    import SortDirectionToggle from '@/Components/SortDirectionToggle.vue';
    import Modal from '@/Components/Modal.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';
    import { chatContentType, chatPreviewText, parseChatMetadata } from './chatMessagePreview.js';

    const props = defineProps({
        rows: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object
        },
        rowCount: {
            type: Number,
            required: true,
        },
        ticketingIsEnabled: {
            type: Boolean
        },
        status: {
            type: String,
        },
        chatSortDirection: {
            type: String
        },
        agents: {
            type: Array,
            default: () => []
        }
    });

    const { t, locale } = useI18n();

    const isSearching = ref(false);
    const showSearchBar = ref(false);
    const selectedContact = ref(null);
    const selectedContacts = ref(new Set());
    const isSelectAll = ref(false);
    const showActions = ref(false);
    const showAssignModal = ref(false);
    const showConfirmModal = ref(false);
    const confirmAction = ref(null);
    const confirmMessage = ref('');
    const processing = ref(false);
    const showActionDropdown = ref(false);
    const showStatusDropdown = ref(false);
    const showFilterModal = ref(false);
    const selectedAction = ref('');
    const hoveredContact = ref(null);
    const selectedAgent = ref(null);
    const agentOptions = ref([]);
    const filterUnread = ref(false);
    const filterAgent = ref(null);
    const filterChannel = ref(null);
    
    // Initialize agent options from props
    const agentSelectOptions = computed(() => {
        return props.agents.map(agent => ({
            label: agent.user ? `${agent.user.first_name} ${agent.user.last_name}` : `Agent ${agent.user_id}`,
            value: agent.user_id
        }));
    });

    const channelSelectOptions = computed(() => [
        { label: t('All Channels'), value: null },
        { label: t('WhatsApp'), value: 'whatsapp', disableTranslation: true },
    ]);
    
    // Permission checks
    const { hasPermission } = useWorkspaceAccess();
    
    const canAssign = computed(() => props.ticketingIsEnabled && hasPermission('chats.assign'));
    const canDelete = computed(() => hasPermission('chats.delete'));
    const canChangeStatus = computed(() => props.ticketingIsEnabled && hasPermission('chats.change_status'));
    
    const emit = defineEmits(['view']);

    function viewChat(contact) {
        selectedContact.value = contact;
        emit('view', contact);
    }

    const contentType = (metadata) => chatContentType(metadata);

    const content = (metadata) => parseChatMetadata(metadata);

    const previewText = (metadata) => chatPreviewText(metadata, t);

    const contactChannel = () => 'whatsapp';

    const contactDisplayName = (contact) => {
        return contact?.full_name
            || '';
    };

    const contactAvatar = (contact) => {
        return contact?.avatar || null;
    };

    const contactInitial = (contact) => {
        return (contactDisplayName(contact) || '?').substring(0, 1).toUpperCase();
    };

    const channelBadge = (contact) => {
        const channel = contactChannel(contact);

        if (channel === 'whatsapp') {
            return {
                label: t('WhatsApp'),
                class: 'bg-green-50 text-green-700 ring-1 ring-green-100',
            };
        }

        return null;
    };

    const getExtension = (fileFormat) => {
        const formatMap = {
            'text/plain': 'TXT',
            'application/pdf': 'PDF',
            'application/vnd.ms-powerpoint': 'PPT',
            'application/msword': 'DOC',
            'application/vnd.ms-excel': 'XLS',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PPTX',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
        };

        return formatMap[fileFormat] || 'Unknown';
    };

    const getContactDisplayName = (metadata) => {
        const contacts = JSON.parse(metadata).contacts;
        if (contacts.length === 1) {
            const contact = contacts[0];
            return contact.name.formatted_name || `${contact.name.first_name} ${contact.name.last_name}`;
        } else if (contacts.length > 1) {
            const firstName = contacts[0].name.first_name;
            const otherContactsCount = contacts.length - 1;
            return t('{name} and {count} other contacts', {
                name: firstName,
                count: otherContactsCount,
            });
        } else {
            return t('No contacts available');
        }
    }

    const getAgentInitials = (user) => {
        if (!user) return '';
        const firstName = user.first_name || '';
        const lastName = user.last_name || '';
        return `${firstName} ${lastName.charAt(0)}`.trim();
    }

    const formatTime = (time) => {
        if(!time){
            return t('Invalid time');
        } else {
            const currentTime = new Date();
            const targetTime = new Date(time);
            const timeDiff = currentTime - targetTime;

            // Check if the time is today
            if (
                targetTime.getDate() === currentTime.getDate() &&
                targetTime.getMonth() === currentTime.getMonth() &&
                targetTime.getFullYear() === currentTime.getFullYear()
            ) {
                return formatDate(targetTime, 'h:mm a');
            }

            // Check if the time is yesterday
            const yesterday = new Date();
            yesterday.setDate(currentTime.getDate() - 1);
            if (
                targetTime.getDate() === yesterday.getDate() &&
                targetTime.getMonth() === yesterday.getMonth() &&
                targetTime.getFullYear() === yesterday.getFullYear()
            ) {
                return t('Yesterday');
            }

            // If beyond yesterday, return the time in the format d/m/y
            return formatDate(targetTime, 'd/m/y');
        }
    };

    const formatDate = (date, format) => {
        let options = null;
        if(format === 'h:mm a'){
            options = { hour12: true, hour: 'numeric', minute: 'numeric' };
        } else {
            options = { day: 'numeric', month: 'numeric', year: 'numeric' };
        }
        
        const activeLocale = locale.value || 'en';
        return new Intl.DateTimeFormat(activeLocale, options).format(date);
    };

    const params = ref({
        search: props.filters.search,
    });

    // Keep search bar open if there's an active search
    watch(() => params.value.search, (newValue) => {
        if (newValue && newValue.trim() !== '') {
            showSearchBar.value = true;
        }
    }, { immediate: true });

    const search = debounce(() => {
        isSearching.value = true;
        runSearch();
    }, 1000);

    const runSearch = () => {
        const url = window.location.pathname;
        const queryParams = new URLSearchParams();
        
        // Add search parameter if it exists
        if (params.value.search && params.value.search.trim() !== '') {
            queryParams.set('search', params.value.search.trim());
        }
        
        // Preserve other query parameters (status, page, etc.)
        const currentParams = new URLSearchParams(window.location.search);
        for (const [key, value] of currentParams.entries()) {
            if (key !== 'search') { // Don't duplicate search
                queryParams.set(key, value);
            }
        }
        
        const queryString = queryParams.toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;

        router.visit(fullUrl, {
            method: 'get',
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
            onError: () => {
                isSearching.value = false;
            }
        })
    }

    const clearSearch = () => {
        params.value.search = null;
        isSearching.value = true;
        const url = window.location.pathname;
        const queryParams = new URLSearchParams(window.location.search);
        queryParams.delete('search');
        
        const queryString = queryParams.toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        
        router.visit(fullUrl, {
            method: 'get',
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isSearching.value = false;
            },
            onError: () => {
                isSearching.value = false;
            }
        })
    }

    const handleClickOutside = (event) => {
        // Close action dropdown when clicking outside
        if (showActionDropdown.value && !event.target.closest('.action-dropdown-container')) {
            showActionDropdown.value = false;
        }
        // Close status dropdown when clicking outside
        if (showStatusDropdown.value && !event.target.closest('.status-dropdown-container')) {
            showStatusDropdown.value = false;
        }
    }

    onMounted(() => {
        document.addEventListener('click', handleClickOutside);
        // Restore selection from localStorage when component mounts
        loadSelectionFromStorage();
        
        // Initialize filters from query params
        const urlParams = new URLSearchParams(window.location.search);
        filterUnread.value = urlParams.get('unread') === '1';
        const channel = urlParams.get('channel');
        filterChannel.value = channel === 'whatsapp' ? channel : null;
        const agentId = urlParams.get('agent');
        if (agentId && props.agents.length > 0) {
            const agent = props.agents.find(a => a.user_id == agentId);
            if (agent && agent.user) {
                filterAgent.value = agent.user_id;
            }
        }
    });

    // Watch for changes in rows data to restore selection for visible contacts
    watch(() => props.rows.data, () => {
        // Restore selection state for contacts on current page
        loadSelectionFromStorage();
    }, { deep: true });

    // Watch for status changes to clear selection when switching statuses
    watch(() => props.status, (newStatus, oldStatus) => {
        if (newStatus !== oldStatus) {
            // Clear selection when status changes
            selectedContacts.value.clear();
            clearSelectionStorage();
            updateSelectAllState();
            updateActionsVisibility();
        }
    });


    // Count active filters
    const activeFilterCount = computed(() => {
        let count = 0;
        if (filterUnread.value) count++;
        if (filterAgent.value !== null) count++;
        if (filterChannel.value !== null) count++;
        return count;
    });

    // Apply filters
    const applyFilters = () => {
        const params = new URLSearchParams(window.location.search);
        
        if (filterUnread.value) {
            params.set('unread', '1');
        } else {
            params.delete('unread');
        }
        
        if (filterAgent.value !== null) {
            params.set('agent', filterAgent.value);
        } else {
            params.delete('agent');
        }

        if (filterChannel.value !== null) {
            params.set('channel', filterChannel.value);
        } else {
            params.delete('channel');
        }
        
        // Preserve other params like status, search, page
        router.visit('/chats?' + params.toString(), {
            preserveState: true,
            preserveScroll: true,
        });
        
        showFilterModal.value = false;
    };

    // Close filter modal
    const closeFilterModal = () => {
        showFilterModal.value = false;
    };

    // Clear filters
    const clearFilters = () => {
        filterUnread.value = false;
        filterAgent.value = null;
        filterChannel.value = null;
        applyFilters();
    };

    // Get chat URL with preserved filter parameters
    const getChatUrl = (contactUuid) => {
        const page = props.rows.meta?.current_page || 1;
        
        // Build query parameters preserving filters
        const filterParams = new URLSearchParams();
        if (filterUnread.value) {
            filterParams.set('unread', '1');
        }
        if (filterAgent.value !== null) {
            filterParams.set('agent', filterAgent.value);
        }
        if (filterChannel.value !== null) {
            filterParams.set('channel', filterChannel.value);
        }
        // Preserve status if exists
        if (props.status && props.status !== 'all') {
            filterParams.set('status', props.status);
        }
        // Preserve search parameter if exists
        if (params.value.search && params.value.search.trim() !== '') {
            filterParams.set('search', params.value.search.trim());
        }
        filterParams.set('page', page);
        
        // Build URL with filters
        const queryString = filterParams.toString();
        return `/chats/${contactUuid}?${queryString}`;
    };

    onBeforeUnmount(() => {
        document.removeEventListener('click', handleClickOutside);
    });

    const getStorageKey = () => {
        const status = props.status || 'all';
        return `chat_selection_${status}`;
    }

    const saveSelectionToStorage = () => {
        const key = getStorageKey();
        const uuids = Array.from(selectedContacts.value);
        localStorage.setItem(key, JSON.stringify(uuids));
    }

    const loadSelectionFromStorage = () => {
        const key = getStorageKey();
        const stored = localStorage.getItem(key);
        if (stored) {
            try {
                const uuids = JSON.parse(stored);
                selectedContacts.value = new Set(uuids);
                updateSelectAllState();
                updateActionsVisibility();
            } catch (e) {
                console.error('Error loading selection from storage:', e);
            }
        }
    }

    const clearSelectionStorage = () => {
        const key = getStorageKey();
        localStorage.removeItem(key);
    }

    const toggleSelect = (contactUuid) => {
        if (selectedContacts.value.has(contactUuid)) {
            selectedContacts.value.delete(contactUuid);
        } else {
            selectedContacts.value.add(contactUuid);
        }
        updateSelectAllState();
        updateActionsVisibility();
        saveSelectionToStorage();
    }

    const toggleSelectAll = () => {
        isSelectAll.value = !isSelectAll.value;
        if (isSelectAll.value) {
            props.rows.data.forEach(contact => {
                selectedContacts.value.add(contact.uuid);
            });
        } else {
            selectedContacts.value.clear();
        }
        updateActionsVisibility();
    }

    const updateSelectAllState = () => {
        if (props.rows.data.length === 0) {
            isSelectAll.value = false;
            return;
        }
        isSelectAll.value = props.rows.data.every(contact => selectedContacts.value.has(contact.uuid));
    }

    const getSelectionState = () => {
        if (props.rows.data.length === 0) {
            return 'none';
        }
        const selectedCount = props.rows.data.filter(contact => selectedContacts.value.has(contact.uuid)).length;
        if (selectedCount === 0) {
            return 'none';
        } else if (selectedCount === props.rows.data.length) {
            return 'all';
        } else {
            return 'some';
        }
    }

    const handleCancelSelection = () => {
        // Cancel entire selection
        selectedContacts.value.clear();
        isSelectAll.value = false;
        showActions.value = false;
        showActionDropdown.value = false;
        clearSelectionStorage();
    }

    const handleSelectionCheckboxClick = () => {
        handleCancelSelection();
    }

    const updateActionsVisibility = () => {
        showActions.value = selectedContacts.value.size > 0;
    }

    // Load all agents for assign modal from props
    onMounted(() => {
        // Use agents from props instead of making a separate API call
        if (props.agents && props.agents.length > 0) {
            agentOptions.value = props.agents
                .filter(agent => agent.user) // Only include agents with user data
                .map(agent => ({
                    label: `${agent.user.first_name} ${agent.user.last_name}`,
                    value: agent.user_id
                }));
        } else {
            // Fallback to API call if props.agents is empty
            fetch("/settings/team?search=", {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(result => {
                    agentOptions.value = result.rows.map(user => ({
                        label: `${user.first_name} ${user.last_name}`,
                        value: user.id
                    }));
                })
                .catch(error => {
                    console.error("Error fetching agents:", error);
                });
        }
    });

    const openAssignModal = () => {
        selectedAgent.value = null;
        showAssignModal.value = true;
    }

    const closeAssignModal = () => {
        selectedAgent.value = { 
            label: '',
            value: null
        };
        showAssignModal.value = false;
    }

    const handleModalClose = () => {
        if (showAssignModal.value) {
            closeAssignModal();
        } else if (showConfirmModal.value) {
            closeConfirmModal();
        }
    }

    const saveAssign = () => {
        if (!selectedAgent.value) {
            alert(t('Please select an agent'));
            return;
        }
        handleAssign(selectedAgent.value);
        closeAssignModal();
    }

    const openConfirmModal = (action, message) => {
        confirmAction.value = action;
        confirmMessage.value = message;
        showConfirmModal.value = true;
    }

    const closeConfirmModal = () => {
        showConfirmModal.value = false;
        confirmAction.value = null;
        confirmMessage.value = '';
    }

    const performBulkAction = async (action, userId = null) => {
        if (selectedContacts.value.size === 0) return;

        // Check permissions before performing action
        if (action === 'assign' && !canAssign.value) {
            alert(t('You do not have permission to assign chats'));
            return;
        }
        if (action === 'clear' && !canDelete.value) {
            alert(t('You do not have permission to delete chats'));
            return;
        }
        if ((action === 'mark_open' || action === 'mark_closed') && !canChangeStatus.value) {
            alert(t('You do not have permission to change chat status'));
            return;
        }

        processing.value = true;
        try {
            const contactUuids = Array.from(selectedContacts.value);
            const response = await axios.post('/chats/bulk-action', {
                action: action,
                contact_uuids: contactUuids,
                user_id: userId
            });

            if (response.data.success) {
                // Clear selection and refresh
                selectedContacts.value.clear();
                isSelectAll.value = false;
                showActions.value = false;
                clearSelectionStorage();
                router.reload();
            } else {
                alert(response.data.message || 'An error occurred');
            }
        } catch (error) {
            console.error('Error performing bulk action:', error);
            alert(error.response?.data?.message || 'An error occurred');
        } finally {
            processing.value = false;
            closeAssignModal();
            closeConfirmModal();
        }
    }

    const handleAssign = (userId) => {
        performBulkAction('assign', userId);
    }

    const handleClear = () => {
        openConfirmModal('clear', `Are you sure you want to clear ${selectedContacts.value.size} chat(s)?`);
    }

    const handleMarkOpen = () => {
        openConfirmModal('mark_open', `Are you sure you want to mark ${selectedContacts.value.size} chat(s) as open?`);
    }

    const handleMarkClosed = () => {
        openConfirmModal('mark_closed', `Are you sure you want to mark ${selectedContacts.value.size} chat(s) as closed?`);
    }

    const handleActionSelect = (action) => {
        selectedAction.value = action;
        showActionDropdown.value = false;

        if (action === 'assign') {
            openAssignModal();
        } else if (action === 'clear') {
            handleClear();
        } else if (action === 'mark_open') {
            handleMarkOpen();
        } else if (action === 'mark_closed') {
            handleMarkClosed();
        }
    }

    const handleDelete = () => {
        handleClear();
    }

    const handleChangeStatus = (status) => {
        showStatusDropdown.value = false;
        if (status === 'open') {
            handleMarkOpen();
        } else if (status === 'closed') {
            handleMarkClosed();
        }
    }

    const confirmActionHandler = () => {
        if (confirmAction.value === 'clear') {
            performBulkAction('clear');
        } else if (confirmAction.value === 'mark_open') {
            performBulkAction('mark_open');
        } else if (confirmAction.value === 'mark_closed') {
            performBulkAction('mark_closed');
        }
    }

    const getAvailableActions = () => {
        const actions = [];
        
        if (canAssign.value) {
            actions.push({ value: 'assign', label: t('Assign to User') });
        }
        
        if (canDelete.value) {
            actions.push({ value: 'clear', label: t('Clear Chats') });
        }
        
        if (canChangeStatus.value) {
            actions.push({ value: 'mark_open', label: t('Mark as Open') });
            actions.push({ value: 'mark_closed', label: t('Mark as Closed') });
        }
        
        return actions;
    }

    const getStatusLabel = () => {
        if (!props.status || props.status === 'all') {
            return '';
        }
        // Capitalize first letter
        return props.status.charAt(0).toUpperCase() + props.status.slice(1);
    }

    const selectAllForCurrentStatus = async () => {
        // Select all chats for the current status across all pages
        try {
            processing.value = true;
            const response = await axios.get('/chats/get-all-uuids', {
                params: {
                    status: props.status || 'all'
                }
            });

            if (response.data.success && response.data.uuids) {
                // Clear current selection
                selectedContacts.value.clear();
                // Add all UUIDs for this status
                response.data.uuids.forEach(uuid => {
                    selectedContacts.value.add(uuid);
                });
                updateSelectAllState();
                updateActionsVisibility();
                saveSelectionToStorage();
            }
        } catch (error) {
            console.error('Error selecting all chats:', error);
            alert(error.response?.data?.message || 'An error occurred while selecting all chats');
        } finally {
            processing.value = false;
        }
    }
</script>
<template>
    <div class="flex h-full min-h-0 flex-col">
    <div class="px-4 py-4 border-b">
        <div class="flex items-center justify-between gap-x-1 text-xl">
            <div class="flex items-end gap-x-1">
                <h2>{{ $t('Chats') }}</h2>
                <span class="text-slate-500 text-sm">{{ rowCount }}</span>
            </div>
            <button type="button" 
                @click="showSearchBar = !showSearchBar" 
                class="p-1.5 rounded hover:bg-gray-100 transition-colors"
                :title="$t('Search')">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-600">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
            </button>
        </div>
        <div v-if="showSearchBar" class="bg-slate-50 rounded-md mt-3 flex items-center py-[2px]">
            <div class="ps-3 py-2">
                <svg class="text-slate-600" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10a7 7 0 1 0 14 0a7 7 0 1 0-14 0m18 11l-6-6"/></svg>
            </div>
            <input @input="search" v-model="params.search" class="w-full bg-slate-50 outline-none rounded-xl py-2 ps-2 me-2 text-sm" type="text" :placeholder="$t('Search name or number...')">
            <button v-if="isSearching === false && params.search" @click="clearSearch" type="button" class="pe-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10s10-4.5 10-10S17.5 2 12 2zm3.7 12.3c.4.4.4 1 0 1.4c-.4.4-1 .4-1.4 0L12 13.4l-2.3 2.3c-.4.4-1 .4-1.4 0c-.4-.4-.4-1 0-1.4l2.3-2.3l-2.3-2.3c-.4-.4-.4-1 0-1.4c.4-.4 1-.4 1.4 0l2.3 2.3l2.3-2.3c.4-.4 1-.4 1.4 0c.4.4.4 1 0 1.4L13.4 12l2.3 2.3z"/></svg>
            </button>
            <span v-if="isSearching" class="pe-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="0 12 12;90 12 12;180 12 12;270 12 12"/><animate attributeName="opacity" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.2s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="30 12 12;120 12 12;210 12 12;300 12 12"/><animate attributeName="opacity" begin="0.2s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle><circle cx="12" cy="3.5" r="1.5" fill="currentColor" opacity="0"><animateTransform attributeName="transform" begin="0.4s" calcMode="discrete" dur="2.4s" repeatCount="indefinite" type="rotate" values="60 12 12;150 12 12;240 12 12;330 12 12"/><animate attributeName="opacity" begin="0.4s" dur="0.6s" keyTimes="0;0.5;1" repeatCount="indefinite" values="1;1;0"/></circle></svg>
            </span>
        </div>
        <div v-if="ticketingIsEnabled" class="grid grid-cols-2 mt-4 items-center w-full">
            <div class="relative ui-layer-dropdown">
                <TicketStatusToggle :status="status" :rowCount="rowCount"/>
            </div>
            <div class="flex ms-auto gap-x-1">
                <!-- Filter Button -->
                <span 
                    @click="showFilterModal = true"
                    class="relative cursor-pointer hover:bg-slate-50 p-1 rounded-full transition-colors"
                    :title="$t('Filters')">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-slate-600 w-5">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                    <!-- Badge showing active filter count -->
                    <span 
                        v-if="activeFilterCount > 0"
                        class="absolute -top-1 -right-1 bg-primary text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                        {{ activeFilterCount }}
                    </span>
                </span>
                <SortDirectionToggle :direction="props.chatSortDirection" :url="'/chats/update-sort-direction'"/>
            </div>
        </div>
    </div>
    <!-- Select All Checkbox in Header -->
    <div v-if="rows.data.length > 0 && !showActions" class="px-4 py-2 border-b bg-gray-50 flex items-center">
        <div 
            @click="toggleSelectAll"
            class="relative w-5 h-5 flex items-center justify-center cursor-pointer me-2 rounded border-2 transition-all"
            :class="isSelectAll ? 'bg-primary border-primary' : 'bg-white border-gray-300 hover:border-primary/50'">
            <svg 
                v-if="isSelectAll"
                xmlns="http://www.w3.org/2000/svg" 
                class="w-3.5 h-3.5 text-white" 
                viewBox="0 0 20 20" 
                fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
        </div>
        <span class="text-sm text-gray-700 cursor-pointer" @click="toggleSelectAll">{{ $t('Select All') }} ({{ rows.data.length }})</span>
    </div>
    <!-- Action Toolbar -->
    <div v-if="showActions" class="px-4 py-2 bg-gray-50 border-b">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2 text-sm text-gray-700">
                <div 
                    @click="handleSelectionCheckboxClick"
                    class="relative w-5 h-5 flex items-center justify-center cursor-pointer rounded border-2 transition-all"
                    :class="{
                        'bg-primary border-primary': getSelectionState() === 'all' || getSelectionState() === 'some',
                        'bg-white border-gray-300 hover:border-primary/50': getSelectionState() === 'none'
                    }">
                    <!-- Checked state (tick) -->
                    <svg 
                        v-if="getSelectionState() === 'all'"
                        xmlns="http://www.w3.org/2000/svg" 
                        class="w-3.5 h-3.5 text-white" 
                        viewBox="0 0 20 20" 
                        fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    <!-- Indeterminate state (dash) -->
                    <svg 
                        v-else-if="getSelectionState() === 'some'"
                        xmlns="http://www.w3.org/2000/svg" 
                        class="w-3.5 h-3.5 text-white flex-shrink-0" 
                        viewBox="0 0 20 20" 
                        fill="currentColor">
                        <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <span>{{ selectedContacts.size }} {{ selectedContacts.size === 1 ? $t('chat') : $t('chats') }} {{ $t('selected') }}</span>
            </div>
            <div class="flex gap-1.5 items-center">
                <!-- Assign Icon Button -->
                <button type="button" 
                    v-if="canAssign"
                    @click.stop="openAssignModal" 
                    class="p-1.5 rounded hover:bg-gray-100 transition-colors relative group"
                    :disabled="processing">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity ui-layer-dropdown">
                        {{ $t('Assign') }}
                    </span>
                </button>
                
                <!-- Change Status Icon Button -->
                <div v-if="canChangeStatus" class="relative status-dropdown-container group">
                    <button type="button" 
                        @click.stop="showStatusDropdown = !showStatusDropdown" 
                        class="p-1.5 rounded hover:bg-gray-100 transition-colors"
                        :disabled="processing">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-primary">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </button>
                    <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity ui-layer-dropdown">
                        {{ $t('Change Status') }}
                    </span>
                    
                    <!-- Status Dropdown Menu -->
                    <div 
                        v-if="showStatusDropdown" 
                        class="ui-dropdown-menu ui-layer-dropdown absolute end-0 mt-2 w-40 rounded-md border border-gray-200"
                        @click.stop>
                        <div class="py-1">
                            <button type="button" 
                                @click="handleChangeStatus('open')"
                                class="w-full text-start px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                {{ $t('Mark as Open') }}
                            </button>
                            <button type="button" 
                                @click="handleChangeStatus('closed')"
                                class="w-full text-start px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                {{ $t('Mark as Closed') }}
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Delete Icon Button -->
                <button type="button" 
                    v-if="canDelete"
                    @click.stop="handleDelete" 
                    class="p-1.5 rounded hover:bg-red-50 transition-colors relative group"
                    :disabled="processing">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-600">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded whitespace-nowrap opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity ui-layer-dropdown">
                        {{ $t('Delete') }}
                    </span>
                </button>
            </div>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-700">
            <span 
                @click="selectAllForCurrentStatus" 
                class="underline cursor-pointer hover:text-primary transition-colors"
                :class="{ 'opacity-50 cursor-not-allowed': processing }">
                {{ $t('Select all') }} {{ getStatusLabel() }} {{ $t('chats') }}
            </span>
            <span class="text-gray-400">|</span>
            <span 
                @click="handleCancelSelection" 
                class="underline cursor-pointer hover:text-primary transition-colors"
                :class="{ 'opacity-50 cursor-not-allowed': processing }">
                {{ $t('Cancel selection') }}
            </span>
        </div>
    </div>
    <div class="flex-grow min-h-0 overflow-y-auto" ref="scrollContainer">
        <!-- Empty State -->
        <div v-if="rows.data.length === 0" class="flex flex-col items-center justify-center h-full py-12">
            <div class="text-gray-400 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            <p class="text-gray-500 text-sm">{{ $t('No chats available') }}</p>
            <p v-if="status && status !== 'all'" class="text-gray-400 text-xs mt-1">{{ $t('No') }} {{ getStatusLabel() }} {{ $t('chats found') }}</p>
        </div>
        
        <!-- Chat List -->
        <div class="block border-b group-hover:pe-0 relative" :class="contact.unread_messages > 0 ? 'bg-green-50' : ''" v-for="(contact, index) in rows.data" :key="index">
            <div class="flex items-start gap-3 hover:bg-gray-50 py-3 px-4 group relative">
                <div class="shrink-0 relative z-10">
                    <!-- Avatar Container (always present for layout) -->
                    <div class="relative w-10 h-10 avatar-container" @mouseenter="hoveredContact = contact.uuid" @mouseleave="hoveredContact = null">
                        <!-- Avatar (always rendered, hidden when selected or on hover) -->
                        <div 
                            class="avatar-display w-10 h-10"
                            :class="{ 'opacity-0': hoveredContact === contact.uuid && !selectedContacts.has(contact.uuid) }">
                            <img v-if="contactAvatar(contact)" class="rounded-full w-10 h-10 object-cover" :src="contactAvatar(contact)" alt="">
                            <div v-else class="rounded-full w-10 h-10 flex items-center justify-center bg-slate-200 capitalize text-sm font-medium">{{ contactInitial(contact) }}</div>
                        </div>
                        
                        <!-- Checkbox Overlay (shown when selected) -->
                        <div 
                            v-if="selectedContacts.has(contact.uuid)"
                            class="absolute inset-0 w-10 h-10 flex items-center justify-center z-20 cursor-pointer rounded-full bg-primary border-2 border-primary transition-all"
                            @click.stop="toggleSelect(contact.uuid)">
                            <svg 
                                xmlns="http://www.w3.org/2000/svg" 
                                class="w-6 h-6 text-white" 
                                viewBox="0 0 20 20" 
                                fill="currentColor">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        
                        <!-- Checkbox on hover (always rendered, shown on hover when not selected) -->
                        <div 
                            v-if="!selectedContacts.has(contact.uuid)"
                            class="absolute inset-0 w-10 h-10 flex items-center justify-center z-10 cursor-pointer rounded-full bg-primary/5 border-2 border-primary/30 transition-all"
                            :class="{ 'opacity-0 pointer-events-none': hoveredContact !== contact.uuid, 'opacity-100': hoveredContact === contact.uuid }"
                            @click.stop="toggleSelect(contact.uuid)">
                        </div>
                    </div>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex items-center gap-2">
                            <h3 class="truncate">{{ contactDisplayName(contact) }}</h3>
                            <span
                                v-if="channelBadge(contact)"
                                class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px]"
                                :class="channelBadge(contact).class"
                            >
                                {{ channelBadge(contact).label }}
                            </span>
                        </div>
                        <span class="shrink-0 text-slate-500 text-xs">{{ formatTime(contact?.last_chat?.created_at) }}</span>
                    </div>
                    <div v-if="contact?.last_chat?.deleted_at === null">
                        <div class="flex items-center justify-between gap-3">
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='text'" class="min-w-0 text-slate-500 text-xs truncate self-end"> {{ content(contact?.last_chat?.metadata).text.body }}</div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='button'" class="min-w-0 text-slate-500 text-xs truncate self-end"> {{ content(contact?.last_chat?.metadata).button.text }}</div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='interactive'" class="min-w-0 text-slate-500 text-xs truncate self-end"> {{ previewText(contact?.last_chat?.metadata) }}</div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='image'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.9 13.98l2.1 2.53l3.1-3.99c.2-.26.6-.26.8.01l3.51 4.68a.5.5 0 0 1-.4.8H6.02c-.42 0-.65-.48-.39-.81L8.12 14c.19-.26.57-.27.78-.02z"/></svg>
                                    <span class="ms-2">{{ $t('Photo') }}</span>
                                </div>
                            </div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='document'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M14.25 2.5a.25.25 0 0 0-.25-.25H7A2.75 2.75 0 0 0 4.25 5v14A2.75 2.75 0 0 0 7 21.75h10A2.75 2.75 0 0 0 19.75 19V9.147a.25.25 0 0 0-.25-.25H15a.75.75 0 0 1-.75-.75V2.5Zm.75 9.75a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1 0-1.5h6Zm0 4a.75.75 0 0 1 0 1.5H9a.75.75 0 0 1 0-1.5h6Z" clip-rule="evenodd"/><path fill="currentColor" d="M15.75 2.824c0-.184.193-.301.336-.186c.121.098.23.212.323.342l3.013 4.197c.068.096-.006.22-.124.22H16a.25.25 0 0 1-.25-.25V2.824Z"/></svg>
                                    <span class="ms-2">{{ getExtension(contact?.last_chat?.media.type) }} {{ $t('File') }}</span>
                                </div>
                            </div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='video'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" d="M3.5 2.5A2.5 2.5 0 0 0 1 5v6a2.5 2.5 0 0 0 2.5 2.5h5A2.5 2.5 0 0 0 11 11V5a2.5 2.5 0 0 0-2.5-2.5h-5Zm10.684 1.61L12 5.893v4.215l2.184 1.78a.5.5 0 0 0 .816-.389v-7a.5.5 0 0 0-.816-.387Z"/></svg>
                                    <span class="ms-2">{{ $t('Video') }}</span>
                                </div>
                            </div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='audio'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 512 512"><path fill="currentColor" d="M256 80C149.9 80 62.4 159.4 49.6 262c9.4-3.8 19.6-6 30.4-6c26.5 0 48 21.5 48 48v128c0 26.5-21.5 48-48 48c-44.2 0-80-35.8-80-80V288C0 146.6 114.6 32 256 32s256 114.6 256 256v112c0 44.2-35.8 80-80 80c-26.5 0-48-21.5-48-48V304c0-26.5 21.5-48 48-48c10.8 0 21 2.1 30.4 6C449.6 159.4 362.1 80 256 80z"/></svg>
                                    <span class="ms-2">{{ $t('Audio') }}</span>
                                </div>
                            </div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='sticker'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 256 256"><path fill="currentColor" d="M168 32H88a56.06 56.06 0 0 0-56 56v80a56.06 56.06 0 0 0 56 56h48a8.07 8.07 0 0 0 2.53-.41c26.23-8.75 76.31-58.83 85.06-85.06A8.07 8.07 0 0 0 224 136V88a56.06 56.06 0 0 0-56-56Zm-32 175.42V176a40 40 0 0 1 40-40h31.42c-9.26 21.55-49.87 62.16-71.42 71.42Z"/></svg>
                                    <span class="ms-2">{{ $t('Sticker') }}</span>
                                </div>
                            </div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='contacts'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" d="M3 14s-1 0-1-1s1-4 6-4s6 3 6 4s-1 1-1 1H3Zm5-6a3 3 0 1 0 0-6a3 3 0 0 0 0 6Z"/></svg>
                                    <span class="ms-2">
                                        {{ getContactDisplayName(contact?.last_chat?.metadata) }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="contentType(contact?.last_chat?.metadata) ==='location'" class="min-w-0 text-slate-500 text-xs truncate self-end"> 
                                <div class="flex items-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" d="M9.156 14.544C10.899 13.01 14 9.876 14 7A6 6 0 0 0 2 7c0 2.876 3.1 6.01 4.844 7.544a1.736 1.736 0 0 0 2.312 0ZM6 7a2 2 0 1 1 4 0a2 2 0 0 1-4 0Z"/></svg>
                                    <span class="ms-2">{{ $t('Location') }}</span>
                                </div>
                            </div>
                            <span v-if="contact.unread_messages > 0" class="shrink-0 bg-green-600 text-white rounded-md py-[1px] px-[8px] min-w-10 text-[10px] flex items-center justify-center">{{ contact.unread_messages }}</span>
                        </div>
                        <div v-if="ticketingIsEnabled && contact?.ticket?.user" class="flex justify-start mt-1">
                            <span 
                                class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-md text-[10px] font-normal bg-primary text-white whitespace-nowrap"
                                :title="`${contact.ticket.user.first_name} ${contact.ticket.user.last_name}`">
                                {{ getAgentInitials(contact.ticket.user) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Clickable area for navigation (when not selecting) -->
            <Link 
                v-if="!showActions"
                :href="getChatUrl(contact.uuid)" 
                class="absolute inset-0 z-0"
                @click.stop="() => {}">
            </Link>
        </div>
    </div>
    <div class="border-t bg-white px-4 py-3">
        <Pagination :pagination="rows.meta"/>
    </div>

    <!-- Assign Modal -->
    <Modal :label="$t('Assign to User')" :isOpen="showAssignModal" :closeBtn="true" @close="closeAssignModal">
        <div class="mt-5">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ $t('Select Agent') }}</label>
                <FormSelect 
                    v-model="selectedAgent" 
                    :name="''" 
                    :options="agentOptions" 
                    :class="'w-full'" 
                    :placeholder="$t('Select Agent')" 
                />
            </div>
            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
                <button type="button" 
                    @click="closeAssignModal" 
                    class="px-3 py-1.5 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors"
                    :disabled="processing">
                    {{ $t('Cancel') }}
                </button>
                <button type="button" 
                    @click="saveAssign" 
                    class="px-3 py-1.5 text-xs bg-primary text-white rounded hover:bg-primary/90 transition-colors"
                    :disabled="processing || !selectedAgent">
                    <span v-if="processing">{{ $t('Saving...') }}</span>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </div>
    </Modal>

    <!-- Confirm Modal for Mark Open/Closed/Delete -->
    <Modal :label="$t('Confirm Action')" :isOpen="showConfirmModal" :closeBtn="true" @close="closeConfirmModal">
        <div class="mt-5">
            <p class="mb-6">{{ confirmMessage }}</p>
            <div class="flex justify-end gap-2">
                <button type="button" 
                    @click="closeConfirmModal" 
                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400"
                    :disabled="processing">
                    {{ $t('Cancel') }}
                </button>
                <button type="button" 
                    @click="confirmActionHandler" 
                    class="px-4 py-2 bg-primary text-white rounded hover:bg-primary/90"
                    :disabled="processing">
                    <span v-if="processing">{{ $t('Processing...') }}</span>
                    <span v-else>{{ $t('Confirm') }}</span>
                </button>
            </div>
        </div>
    </Modal>

    <!-- Advanced Filter Modal -->
    <Modal :label="$t('Advanced Filters')" :isOpen="showFilterModal" :closeBtn="true" @close="closeFilterModal">
        <div class="mt-5 space-y-6">
            <!-- Unread Messages Filter -->
            <div>
                <label class="flex items-center gap-x-2 cursor-pointer">
                    <div 
                        @click="filterUnread = !filterUnread"
                        class="relative w-5 h-5 flex items-center justify-center cursor-pointer rounded border-2 transition-all"
                        :class="filterUnread ? 'bg-primary border-primary' : 'bg-white border-gray-300 hover:border-primary/50'">
                        <svg 
                            v-if="filterUnread"
                            xmlns="http://www.w3.org/2000/svg" 
                            class="w-3.5 h-3.5 text-white" 
                            viewBox="0 0 20 20" 
                            fill="currentColor">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <span class="text-sm text-gray-700 font-medium cursor-pointer" @click="filterUnread = !filterUnread">{{ $t('Unread Messages') }}</span>
                </label>
                <p class="text-xs text-gray-500 mt-1 ms-7">{{ $t('Show only chats with unread messages') }}</p>
            </div>

            <!-- Channel Filter -->
            <div>
                <label class="block text-sm text-gray-700 font-medium mb-2">{{ $t('Channel') }}</label>
                <FormSelect
                    v-model="filterChannel"
                    :options="channelSelectOptions"
                    :placeholder="$t('All Channels')"
                    class="w-full"
                />
                <p class="text-xs text-gray-500 mt-1">{{ $t('Show WhatsApp conversations.') }}</p>
            </div>
            
            <!-- Agent Filter -->
            <div v-if="ticketingIsEnabled && agents.length > 0">
                <label class="block text-sm text-gray-700 font-medium mb-2">{{ $t('Agent') }}</label>
                <FormSelect
                    v-model="filterAgent"
                    :options="[{ label: $t('All Agents'), value: null }, ...agentSelectOptions]"
                    :placeholder="$t('All Agents')"
                    class="w-full"
                />
                <p class="text-xs text-gray-500 mt-1">{{ $t('Filter chats by assigned agent') }}</p>
            </div>
            
            <div v-if="!ticketingIsEnabled || agents.length === 0" class="text-sm text-gray-500 italic">
                {{ $t('Agent filter is only available when ticketing is enabled') }}
            </div>
        </div>
        
        <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
            <button type="button" 
                @click="clearFilters" 
                class="px-3 py-1.5 text-xs bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition-colors">
                {{ $t('Clear All') }}
            </button>
            <button type="button" 
                @click="applyFilters" 
                class="px-3 py-1.5 text-xs bg-primary text-white rounded hover:bg-primary/90 transition-colors">
                {{ $t('Apply Filters') }}
            </button>
        </div>
    </Modal>
    </div>
</template>
  
