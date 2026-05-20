<template>
    <AppLayout v-slot:default="slotProps">
        <div class="ui-page ui-fade-up ui-page-frame ui-workspace-page--fixed min-h-full">
            <div class="ui-workspace-split ui-workspace-split--chat h-full">
                <div class="ui-workspace-pane ui-workspace-sidebar" :class="contact ? 'hidden' : ''">
                    <ChatTable :rows="rows" :filters="props.filters" :rowCount="props.rowCount" :ticketingIsEnabled="ticketingIsEnabled" :status="props?.status" :chatSortDirection="props.chat_sort_direction" :agents="props.agents"/>
                </div>
                <div class="ui-workspace-pane ui-workspace-main ui-workspace-main--chat min-w-0 flex h-full min-h-0 flex-1 flex-col">
                    <ChatHeader
                    v-if="contact" 
                    :ticketingIsEnabled="ticketingIsEnabled" 
                    :contact="activeContact"
                    :activeConversationChannel="activeConversationChannel"
                    :displayContactInfo="displayContactInfo" 
                    :ticket="ticket" 
                    :addon="addon" 
                    @toggleView="toggleContactView" 
                    @deleteThread="deleteThread" 
                    @closeThread="closeThread"
                />
                <div v-if="contact && !displayTemplate" class="flex-1 overflow-y-auto" ref="scrollContainer2">
                    <ChatThread 
                        v-if="!displayContactInfo && !loadingThread && !displayTemplate"
                        :contactId="activeContact.id"
                        :initialMessages="chatThread"
                        :hasMoreMessages="hasMoreMessages"
                        :initialNextPage="nextPage"
                        @messagesLoaded="replaceChatThread"
                    />
                    <Contact 
                        v-if="displayContactInfo && !displayTemplate" 
                        class="bg-white h-full" 
                        :fields="props.fields" 
                        :contact="activeContact"
                        :locationSettings="props.locationSettings" 
                    />
                </div>
                <div v-if="contact && !displayContactInfo && !formLoading && !displayTemplate" class="w-full py-4">
                    <ChatForm 
                        :contact="activeContact"
                        :activeConversationChannel="activeConversationChannel"
                        :showAiAssist="showAiAssist" 
                        :chatLimitReached="isChatLimitReached" 
                        @viewTemplate="displayTemplate = true;"
                    />
                </div>
                <div v-if="displayTemplate && canReply" class="flex-1 overflow-y-hidden">
                    <CampaignForm
                        v-if="displayTemplate && canReply"
                        class="h-full"
                        :contact="activeContact.uuid"
                        :templates="templates" 
                        :contactGroups="[]" 
                        :contactFields="props.contactFields"
                        :settings="props.settings" 
                        :displayCancelBtn="false" 
                        :displayTitle="true" 
                        :isCampaignFlow="false" 
                        :scheduleTemplate="false" 
                        :sendText="t('Send Template')" 
                        @viewTemplate="displayTemplate = false;"
                    />
                </div>
                <div v-else-if="!contact" class="hidden h-full md:flex">
                    <div class="ui-workspace-empty-state ui-workspace-empty-state--chat">
                        <div class="ui-workspace-empty ui-workspace-empty--chat text-center">
                            <span class="ui-workspace-empty-kicker">{{ $t('Chats') }}</span>
                            <h2 class="ui-workspace-empty-title">{{ $t('Select chat') }}</h2>
                            <p class="ui-workspace-empty-description">{{ $t('Choose a conversation from the list to review messages, ticket status, and send a reply from one place.') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <!--<div v-if="contact" class="md:w-[25%] min-w-0 bg-cover flex flex-col bg-white border-s">
                <ChatContact v-if="contact" class="bg-white h-full" :contact="contact" />
            </div>-->
            </div>
        </div>
        <button type="button" class="hidden" ref="toggleNavbarBtn" @click="slotProps.toggleNavBar"></button>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "./../Layout/App.vue";
    import { default as axios } from 'axios';
    import { router, usePage } from '@inertiajs/vue3';
    import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
    import CampaignForm from '@/Components/CampaignForm.vue';
    import ChatForm from '@/Components/ChatComponents/ChatForm.vue';
    import ChatHeader from '@/Components/ChatComponents/ChatHeader.vue';
    import ChatTable from '@/Components/ChatComponents/ChatTable.vue';
    import ChatThread from '@/Components/ChatComponents/ChatThread.vue';
    import ChatContact from '@/Components/ChatComponents/ChatContact.vue';
    import Contact from '@/Components/ContactInfo.vue';
    import { getEchoInstance } from '../../../echo';
    import { useI18n } from 'vue-i18n';
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';

    const { t } = useI18n();
    const page = usePage();
    const { hasPermission, isWorkspaceOwner } = useWorkspaceAccess();

    const props = defineProps({
        rows: Array,
        rowCount: Number,
        pusherSettings: Object,
        organizationId: Number,
        isChatLimitReached: Boolean,
        toggleNavBar: Function,
        state: String,
        demoNumber: String,
        settings: Object,
        status: String,
        chatThread: Array,
        hasMoreMessages: Boolean,
        nextPage: Number,
        addon: Object,
        contact: Object,
        ticket: Object,
        chat_sort_direction: String,
        filters: Object,
        templates: Array,
        contactFields: {
            type: Array,
            default: () => []
        },
        fields: Array,
        locationSettings: Object,
        showAiAssist: Boolean,
        agents: Array
    });

    const rows = ref(props.rows);
    const rowCount = ref(props.rowCount);
    const scrollContainer2 = ref(null);
    const loadingThread = ref(false);
    const displayContactInfo = ref(false);
    const displayTemplate = ref(false);
    const formLoading = ref(false);
    const isChatLimitReached = ref(props.isChatLimitReached);
    const toggleNavbarBtn = ref(null);
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const ticketingIsEnabled = ref(settings.value?.tickets?.active ?? false);
    const chatThread = ref(props.chatThread);
    const contact = ref(props.contact);
    const canReply = computed(() => hasPermission('chats.reply'));
    const selectedRowContact = computed(() => {
        if (!contact.value?.uuid || !Array.isArray(rows.value?.data)) {
            return null;
        }

        return rows.value.data.find(row => row?.uuid === contact.value.uuid) || null;
    });
    const activeContact = computed(() => {
        if (!contact.value) {
            return null;
        }

        const rowContact = selectedRowContact.value;
        if (!rowContact) {
            return contact.value;
        }

        return {
            ...rowContact,
            ...contact.value,
            last_chat: contact.value.last_chat || rowContact.last_chat,
            last_inbound_chat: contact.value.last_inbound_chat || rowContact.last_inbound_chat,
        };
    });
    const activeConversationChannel = computed(() => 'whatsapp');
    const activeChatChannelName = computed(() => {
        const userId = page.props?.auth?.user?.id;
        const shouldUseAssignedStream = !isWorkspaceOwner.value && hasPermission('chats.view_assigned_only');

        if (shouldUseAssignedStream && userId) {
            return `chats.user.${userId}`;
        }

        return props.organizationId ? `chats.ch${props.organizationId}` : null;
    });

    let echoClient = null;
    let subscribedChatChannelName = null;

    watch(() => props.rows, (newRows) => {
        rows.value = newRows;
    });

    const toggleDropdown = () => {
        isOpen.value = !isOpen.value;
    }

    function toggleContactView(value) {
        displayContactInfo.value = value;
    }

    const scrollToBottom = () => {
        const container = scrollContainer2.value;
        if (container) {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth',
            });
        }
    };

    watch(() => props.contact, (newContact, oldContact) => {
        const nextContact = newContact || null;
        const nextUuid = nextContact?.uuid || null;
        const previousUuid = oldContact?.uuid || contact.value?.uuid || null;

        contact.value = nextContact;

        if (nextUuid !== previousUuid) {
            displayContactInfo.value = false;
            displayTemplate.value = false;
            loadingThread.value = false;
            formLoading.value = false;
        }
    }, { immediate: true, deep: true });

    watch(() => props.chatThread, (newMessages) => {
        chatThread.value = Array.isArray(newMessages) ? newMessages : [];

        if (contact.value && !displayContactInfo.value && !displayTemplate.value) {
            setTimeout(scrollToBottom, 100);
        }
    }, { immediate: true, deep: true });

    watch(() => props.isChatLimitReached, (newValue) => {
        isChatLimitReached.value = newValue;
    });

    const closeThread = () => {
        toggleNavbarBtn.value.click();
        contact.value = null;
        
        // Preserve filter parameters when navigating back
        const params = new URLSearchParams(window.location.search);
        const filterParams = new URLSearchParams();
        
        // Preserve unread filter
        if (params.get('unread') === '1') {
            filterParams.set('unread', '1');
        }
        
        // Preserve agent filter
        if (params.get('agent')) {
            filterParams.set('agent', params.get('agent'));
        }

        // Preserve status filter
        if (params.get('status') && params.get('status') !== 'all') {
            filterParams.set('status', params.get('status'));
        }
        
        // Navigate back with filters preserved
        const queryString = filterParams.toString();
        const url = queryString ? `/chats?${queryString}` : '/chats';
        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
        });
    }

    const deleteThread = async () => {
        chatThread.value = [];
        await axios.delete('/chats/' + contact.value.uuid);
    }

    const replaceChatThread = (messages) => {
        chatThread.value = messages;
    }

    const mergeSelectedContact = (freshContact) => {
        if (!contact.value || !freshContact?.uuid || freshContact.uuid !== contact.value.uuid) {
            return;
        }

        contact.value = {
            ...contact.value,
            ...freshContact,
            notes: contact.value.notes ?? freshContact.notes,
            contact_groups: contact.value.contact_groups ?? freshContact.contact_groups,
        };
    }

    const mergeSelectedContactFromRows = (freshRows) => {
        if (!contact.value || !Array.isArray(freshRows)) {
            return;
        }

        const freshContact = freshRows.find(row => row?.uuid === contact.value.uuid);
        mergeSelectedContact(freshContact);
    }

    const selectedContactRefreshUrl = () => {
        if (!contact.value?.uuid) {
            return `/chats${window.location.search || ''}`;
        }

        return `/chats/${contact.value.uuid}${window.location.search || ''}`;
    }

    const syncSelectedContactChannel = (chatValue) => {
        const selectedContactId = Number(contact.value?.id);
        const chatContactId = Number(chatValue?.contact_id);

        if (!contact.value || !chatValue || !Number.isFinite(selectedContactId) || selectedContactId !== chatContactId) {
            return;
        }

        contact.value = {
            ...contact.value,
            last_chat: {
                ...(contact.value.last_chat || {}),
                ...chatValue,
            },
        };
    }

    const latestThreadChatValueForSelectedContact = () => {
        if (!contact.value?.id || !Array.isArray(chatThread.value)) {
            return null;
        }

        const selectedContactId = Number(contact.value.id);
        for (let index = chatThread.value.length - 1; index >= 0; index -= 1) {
            const value = Array.isArray(chatThread.value[index])
                ? chatThread.value[index][0]?.value
                : chatThread.value[index]?.value;

            if (!value || Number(value.contact_id) !== selectedContactId) {
                continue;
            }

            return value;
        }

        return null;
    }

    watch([() => contact.value?.uuid, () => chatThread.value], () => {
        const latestChatValue = latestThreadChatValueForSelectedContact();

        if (latestChatValue) {
            syncSelectedContactChannel(latestChatValue);
        }
    }, { immediate: true, deep: true });

    const updateChatThread = (chat) => {
        if (!Array.isArray(chat) || !chat[0]?.value) {
            return;
        }

        const chatValue = chat[0].value;
        syncSelectedContactChannel(chatValue);
        const wamId = chatValue.wam_id;
        const chatId = chatValue.id;
        
        // For failed messages (wam_id is null), check by chat ID instead
        // For successful messages, check by wam_id
        const existingChatIndex = wamId 
            ? chatThread.value.findIndex(existingChat => existingChat[0].value.wam_id === wamId)
            : chatThread.value.findIndex(existingChat => existingChat[0].value.id === chatId);

        if (existingChatIndex !== -1) {
            // Update existing message (e.g., status update)
            chatThread.value[existingChatIndex] = chat;
        } else if (chatValue.deleted_at == null) {
            // Add new message
            chatThread.value.push(chat);
            setTimeout(scrollToBottom, 100);
        }
    }

    // Debounce timer for updateSidePanel to prevent rapid requests
    let updateSidePanelTimer = null;
    
    const updateSidePanel = async(chat) => {
        if (!Array.isArray(chat) || !chat[0]?.value) {
            return;
        }

        if(contact.value && contact.value.id == chat[0].value.contact_id){
            updateChatThread(chat);
        }

        // Debounce: Clear existing timer and set new one
        // This prevents multiple rapid requests when multiple broadcasts arrive
        if (updateSidePanelTimer) {
            clearTimeout(updateSidePanelTimer);
        }
        
        // Wait 500ms before making the request
        // If another broadcast arrives within 500ms, cancel previous and restart timer
        updateSidePanelTimer = setTimeout(async () => {
        try {
            const response = await axios.get(selectedContactRefreshUrl());
            if (response?.data?.result) {
                rows.value = response.data.result;
                mergeSelectedContactFromRows(response.data.result?.data || []);
            }
            if (response?.data?.contact) {
                mergeSelectedContact(response.data.contact);
            }
        } catch (error) {
            console.error('Error updating side panel:', error);
        }
            updateSidePanelTimer = null;
        }, 500);
    }

    const onCloseDemoModal = () => {
        isDemoModalOpen.value = false;
    }

    const subscribeToChatStream = () => {
        const channelName = activeChatChannelName.value;

        if (!echoClient || !channelName) {
            return;
        }

        if (subscribedChatChannelName === channelName) {
            return;
        }

        if (subscribedChatChannelName) {
            echoClient.leave(subscribedChatChannelName);
        }

        echoClient.private(channelName)
            .listen('NewChatEvent', (event) => {
                updateSidePanel(event.chat);
            });
        subscribedChatChannelName = channelName;
    }

    const unsubscribeFromChatStream = () => {
        if (!echoClient || !subscribedChatChannelName) {
            return;
        }

        echoClient.leave(subscribedChatChannelName);
        subscribedChatChannelName = null;
    }

    onMounted(() => {
        echoClient = getEchoInstance(
            props.pusherSettings['pusher_app_key'],
            props.pusherSettings['pusher_app_cluster']
        );

        if (!echoClient) {
            scrollToBottom();
            return;
        }

        subscribeToChatStream();

        scrollToBottom();
    });

    onUnmounted(() => {
        unsubscribeFromChatStream();

        if (updateSidePanelTimer) {
            clearTimeout(updateSidePanelTimer);
            updateSidePanelTimer = null;
        }
    });
</script>
