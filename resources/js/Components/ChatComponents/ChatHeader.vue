<script setup>
    import { ref, watchEffect, computed, onMounted } from 'vue';
    import axios from 'axios';
    import { router, useForm, Link, usePage } from '@inertiajs/vue3'
    import AlertModal from '@/Components/AlertModal.vue';
    import Dropdown from '@/Components/Dropdown.vue';
    import DropdownItemGroup from '@/Components/DropdownItemGroup.vue';
    import DropdownItem from '@/Components/DropdownItem.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import Modal from '@/Components/Modal.vue';
    import { useI18n } from 'vue-i18n';
    import { useWorkspaceAccess } from '@/Composables/useWorkspaceAccess';
    const { t } = useI18n();

    const props = defineProps(['contact', 'displayContactInfo', 'ticketingIsEnabled', 'ticket', 'addon', 'activeConversationChannel', 'aiStatus']);

    const accountUser = computed(() => usePage().props.auth.user);
    const page = usePage();
    const { hasPermission } = useWorkspaceAccess();
    
    // Get back URL preserving search and other query parameters
    const getBackUrl = () => {
        const urlParams = new URLSearchParams(window.location.search);
        const backParams = new URLSearchParams();
        
        // Preserve search parameter if it exists
        if (urlParams.has('search')) {
            backParams.set('search', urlParams.get('search'));
        }
        // Preserve other query parameters
        if (urlParams.has('unread')) {
            backParams.set('unread', urlParams.get('unread'));
        }
        if (urlParams.has('agent')) {
            backParams.set('agent', urlParams.get('agent'));
        }
        if (urlParams.has('channel')) {
            backParams.set('channel', urlParams.get('channel'));
        }
        if (urlParams.has('status')) {
            backParams.set('status', urlParams.get('status'));
        }
        if (urlParams.has('page')) {
            backParams.set('page', urlParams.get('page'));
        }
        
        const queryString = backParams.toString();
        return queryString ? `/chats?${queryString}` : '/chats';
    };
    
    const canAssign = computed(() => props.ticketingIsEnabled && hasPermission('chats.assign'));
    const canDelete = computed(() => hasPermission('chats.delete'));
    const canChangeStatus = computed(() => props.ticketingIsEnabled && hasPermission('chats.change_status'));
    const canManageAiAssistant = computed(() => hasPermission('automations.edit'));
    const supportedConversationChannels = ['whatsapp'];
    const normalizeConversationChannel = (channel) => {
        const normalized = String(channel || '').trim().toLowerCase();
        return supportedConversationChannels.includes(normalized) ? normalized : null;
    };
    const activeChannel = computed(() => {
        return normalizeConversationChannel(props.activeConversationChannel) || 'whatsapp';
    });
    const isWebVisitor = computed(() => props.contact?.channel === 'web');
    // Conversations opened through the Mochi API carry the name of the app they came from.
    const sourceApp = computed(() => {
        try {
            const metadata = typeof props.contact?.metadata === 'string' ? JSON.parse(props.contact.metadata) : props.contact?.metadata;
            return metadata?.source_app || null;
        } catch (error) {
            return null;
        }
    });
    const activeChannelLabel = computed(() => {
        if (!isWebVisitor.value) {
            return t('WhatsApp');
        }

        return sourceApp.value ? `${t('App')}: ${sourceApp.value}` : t('Website');
    });
    const showChannelBadge = computed(() => isWebVisitor.value);
    const contactDisplayName = computed(() => props.contact?.full_name || '');
    const contactAvatar = computed(() => props.contact?.avatar || '');
    const contactInitial = computed(() => (contactDisplayName.value || '?').substring(0, 1).toUpperCase());
    const contactSubtitle = computed(() => {
        if (isWebVisitor.value) {
            return sourceApp.value ? t('Chatting from your app through the Mochi API') : t('Chatting from your website widget');
        }

        if (props.contact?.formatted_phone_number) {
            return props.contact.formatted_phone_number;
        }

        return '';
    });
    
    const showAlert = ref(false);
    const displayContact = ref(props.displayContactInfo);
    const ticketState = ref(null);
    const isOpenModal = ref(false);
    const user = ref(props.ticket?.user ? props.ticket?.user?.id : null);
    const userOptions = ref([]);

    watchEffect(() => {
        displayContact.value = props.displayContactInfo;
        ticketState.value = props.ticket?.status;
        // Update user value when ticket changes
        if (props.ticket?.user) {
            user.value = props.ticket.user.id;
            // If userOptions are already loaded, ensure the assigned user is in the list
            if (userOptions.value.length > 0) {
                const userExists = userOptions.value.some(opt => opt.value === props.ticket.user.id);
                if (!userExists) {
                    userOptions.value.push({
                        label: `${props.ticket.user.first_name} ${props.ticket.user.last_name}`,
                        value: props.ticket.user.id
                    });
                }
            }
        } else {
            user.value = null;
        }
    });

    const emit = defineEmits(['toggleView', 'deleteThread', 'closeThread']);

    const closeThread = () => {
        emit('closeThread', true);
    }

    const toggleView = () => {
        displayContact.value = !displayContact.value;
        emit('toggleView', displayContact.value);
    }

    const deleteThread = () => {
        if (!canDelete.value) {
            alert(t('You do not have permission to delete chats'));
            showAlert.value = false;
            return;
        }
        router.visit('/chats/' + props.contact.uuid, {
            method: 'delete',
            onFinish: () => {
                showAlert.value = false
            }
        });
    }

    const form = useForm({
        'status' : ticketState
    });

    const form2 = useForm({
        'notes': null,
        'contact': null
    });

    const form3 = useForm({
        'ai_assistant': false,
    });

    // The strip shows what the assistant will really do here, not just this conversation's own switch.
    const aiState = computed(() => props.aiStatus?.state ?? null);
    const aiOffEverywhere = computed(() => aiState.value === 'off_everywhere');
    const aiOn = computed(() => aiState.value === 'on');
    watchEffect(() => {
        form3.ai_assistant = aiOn.value;
    });

    const pausedUntilText = computed(() => {
        const value = props.aiStatus?.paused_until;
        if (!value) {
            return '';
        }
        const date = new Date(value);
        return Number.isNaN(date.getTime())
            ? ''
            : date.toLocaleTimeString(document.documentElement.lang || undefined, { hour: 'numeric', minute: '2-digit' });
    });

    const aiTitle = computed(() => ({
        on: t('AI assistant is replying in this conversation'),
        off_everywhere: t('AI assistant is off for all conversations'),
        waiting_team: t('AI assistant is stopped: the customer asked for a person'),
        team_replied: t('AI assistant is stopped because the team replied'),
        off_here: t('AI assistant is off for this conversation'),
    }[aiState.value] ?? ''));

    const aiHint = computed(() => {
        switch (aiState.value) {
            case 'off_everywhere':
                return t('It was turned off from the AI assistant page.');
            case 'waiting_team':
                return t('It stays stopped until someone replies or turns it back on.');
            case 'team_replied':
                return pausedUntilText.value
                    ? t('It comes back on its own at {time}.', { time: pausedUntilText.value })
                    : '';
            default:
                return '';
        }
    });

    const changeTicketStatus = (value) => {
        if (!canChangeStatus.value) {
            alert(t('You do not have permission to change chat status'));
            return;
        }
        router.put('/tickets/' + props.contact.uuid + '/update', {
            'status' : value
        }, {})
    }

    const changeTicketAgent = () => {
        if (!canAssign.value) {
            alert(t('You do not have permission to assign chats'));
            return;
        }
        router.put('/tickets/' + props.contact.uuid + '/assign', {
            'id' : user.value
        }, {})
    }

    // Load all users for the select dropdown
    onMounted(() => {
        fetch("/settings/team?search=", {
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(result => {
                const options = [
                    { label: t('Unassigned'), value: null },
                    ...result.rows.map(u => ({
                        label: `${u.first_name} ${u.last_name}`,
                        value: u.id
                    }))
                ];
                
                // If there's a currently assigned user that's not in the list, add them
                if (props.ticket?.user && !result.rows.find(u => u.id === props.ticket.user.id)) {
                    options.push({
                        label: `${props.ticket.user.first_name} ${props.ticket.user.last_name}`,
                        value: props.ticket.user.id
                    });
                }
                
                userOptions.value = options;
                
                // Ensure the selected value is set after options are loaded
                if (props.ticket?.user) {
                    user.value = props.ticket.user.id;
                } else {
                    user.value = null;
                }
            })
            .catch(error => {
                console.error("Error fetching agents:", error);
            });
    });


    const submitForm = () => {
        form2.contact = props.contact.uuid;

        form2.post('/notes', {
            preserveState: false,
            onSuccess: () => {
                form2.reset();
                isOpenModal.value = false;
            }
        });
    }

    const submitForm3 = () => {
        if (aiOffEverywhere.value) {
            return;
        }
        form3.post('/automation/contact/' + props.contact.uuid, {
            preserveState: true,
            preserveScroll: true,
            // Show the saved state again, whether the save worked or not.
            onFinish: () => { form3.ai_assistant = aiOn.value; },
        });
    }
</script>
<template>
    <div class="h-20 bg-white dark:bg-[#111b21] border-b border-slate-100 dark:border-white/10 flex items-center justify-between px-4 md:px-5">
        <div class="flex items-center gap-x-3 cursor-pointer w-3/4">
            <Link :href="getBackUrl()" class="sm:block md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" width="2em" height="2em" viewBox="0 0 24 24"><path fill="currentColor" d="M19 11H7.14l3.63-4.36a1 1 0 1 0-1.54-1.28l-5 6a1.19 1.19 0 0 0-.09.15c0 .05 0 .08-.07.13A1 1 0 0 0 4 12a1 1 0 0 0 .07.36c0 .05 0 .08.07.13a1.19 1.19 0 0 0 .09.15l5 6A1 1 0 0 0 10 19a1 1 0 0 0 .64-.23a1 1 0 0 0 .13-1.41L7.14 13H19a1 1 0 0 0 0-2"/></svg>
            </Link>
            <div @click="toggleView">
                <img v-if="contactAvatar" class="rounded-full w-12 h-12 object-cover" :src="contactAvatar" alt="">
                <div v-else class="rounded-full w-11 h-11 flex items-center justify-center bg-slate-100 dark:bg-white/10 font-medium">{{ contactInitial }}</div>
            </div>
            <div class="flex items-center w-full gap-x-8">
                <div class="min-w-0">
                    <div class="flex items-center gap-2" @click="toggleView">
                        <span class="font-bold text-[17px] truncate">{{ contactDisplayName }}</span>
                        <span v-if="showChannelBadge" class="rounded-md bg-slate-100 dark:bg-white/10 px-2 py-0.5 text-[11px] text-slate-700 dark:text-slate-200">{{ activeChannelLabel }}</span>
                    </div>
                    <div class="w-full flex items-center text-[13px] font-medium text-slate-500 dark:text-slate-400">
                        <span @click="toggleView">{{ contactSubtitle }}</span>
                    </div>
                </div>
                <FormSelect v-if="canAssign" v-model="user" :name="''" :options="userOptions" :class="'col-span-1 md:block hidden'" :placeholder="$t('Select Agent')" @update:modelValue="changeTicketAgent()"/>
            </div>
        </div>
        <div>
            <div class="flex items-center gap-x-3">
                <button type="button" v-if="ticketState === 'open' && canChangeStatus" @click="changeTicketStatus('closed')" class="text-sm font-medium md:inline-flex hidden justify-center rounded-lg border border-transparent bg-red-600 hover:bg-red-700 transition-colors px-4 py-1.5 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">{{ $t('Mark as closed') }}</button>
                <button type="button" v-if="ticketState === 'closed' && canChangeStatus" @click="changeTicketStatus('open')" class="text-sm font-medium md:inline-flex hidden justify-center rounded-lg border border-transparent bg-primary hover:opacity-90 transition-opacity px-4 py-1.5 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">{{ $t('Mark as open') }}</button>

                <Dropdown v-if="!displayContact">
                    <button type="button" class="inline-flex w-full justify-center items-center rounded-md text-sm font-medium text-black dark:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                        <span class="chat-header-icon-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.75a.75.75 0 1 1 0-1.5a.75.75 0 0 1 0 1.5Zm0 6a.75.75 0 1 1 0-1.5a.75.75 0 0 1 0 1.5Zm0 6a.75.75 0 1 1 0-1.5a.75.75 0 0 1 0 1.5Z"/></svg>
                        </span>
                    </button>
                    <template #items>
                        <DropdownItemGroup>
                            <DropdownItem @click="isOpenModal = true;" as="button">{{ $t('Add notes') }}</DropdownItem>
                            <DropdownItem v-if="ticketState === 'open' && canChangeStatus" @click="changeTicketStatus('closed')" as="button">{{ $t('Mark as closed') }}</DropdownItem>
                            <DropdownItem v-if="ticketState === 'closed' && canChangeStatus" @click="changeTicketStatus('open')" as="button">{{ $t('Mark as open') }}</DropdownItem>
                            <DropdownItem v-if="canDelete" @click="showAlert = true" as="button">{{ $t('Clear chat') }}</DropdownItem>
                        </DropdownItemGroup>
                    </template>
                </Dropdown>
                <div v-else @click="toggleView" class="bg-slate-100 dark:bg-white/10 hover:bg-slate-200 dark:hover:bg-white/15 transition-colors px-3 py-2 rounded-lg text-sm cursor-pointer">
                    <span>{{ $t('Back') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div v-if="addon == 1 && aiState" class="ai-strip" :class="`ai-strip--${aiOn ? 'on' : (aiState === 'off_everywhere' || aiState === 'off_here' ? 'off' : 'paused')}`" data-ai-state-strip :data-state="aiState">
        <div class="ai-strip__text" role="status" aria-live="polite">
            <span class="ai-strip__dot" aria-hidden="true"></span>
            <div class="min-w-0">
                <p class="ai-strip__title">{{ aiTitle }}</p>
                <p v-if="aiHint" class="ai-strip__hint">{{ aiHint }}</p>
            </div>
        </div>
        <div v-if="canManageAiAssistant" class="ai-strip__actions">
            <Link v-if="aiOffEverywhere" href="/automation/ai" class="ai-strip__link">{{ $t('Turn it on') }}</Link>
            <template v-else>
                <span class="ai-strip__label" aria-hidden="true">{{ $t('In this conversation') }}</span>
                <FormToggleSwitch
                    v-model="form3.ai_assistant"
                    :disabled="form3.processing"
                    :aria-label="$t('AI assistant in this conversation')"
                    class="ai-strip__toggle"
                    @update:modelValue="submitForm3"
                />
            </template>
        </div>
    </div>

    <Modal :label="$t('Add Note')" :isOpen="isOpenModal" @close="isOpenModal = false">
        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <form @submit.prevent="submitForm()" class="">
                <FormTextArea v-model="form2.notes" :error="form2.errors.note" :name="''" :class="'col-span-2'"/>
                <div class="mt-4 flex">
                    <button type="button" @click="isOpenModal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 dark:bg-white/5 px-4 py-2 text-sm text-slate-500 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
                    <button type="submit" 
                        :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form2.processing }]"
                        :disabled="form2.processing"
                    >
                        <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </Modal>

    <AlertModal
        v-model="showAlert"
        :label="$t('Clear chat')"
        :description="$t('Are you sure you want to delete this thread? You can\'t undo this action')"
        @confirm="deleteThread" />
</template>

<style scoped>
.chat-header-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 999px;
    cursor: pointer;
    color: var(--ui-muted);
    background: transparent;
    transition: background-color 160ms ease, color 160ms ease;
}

.ai-strip {
    --ai-tone: var(--ui-success);
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 16px;
    padding: 8px 16px; border-bottom: 1px solid var(--ui-border);
    background: color-mix(in srgb, var(--ai-tone) 8%, var(--ui-surface));
    border-inline-start: 3px solid var(--ai-tone);
}
.ai-strip--off { --ai-tone: var(--ui-danger); }
.ai-strip--paused { --ai-tone: var(--ui-warning); }
.ai-strip__text { display: flex; align-items: flex-start; gap: 10px; min-width: 0; flex: 1 1 220px; }
.ai-strip__dot { width: 10px; height: 10px; margin-top: 6px; border-radius: 999px; background: var(--ai-tone); flex: none; }
.ai-strip__title { margin: 0; font-size: 14px; font-weight: 600; line-height: 1.5; color: var(--ui-text); }
.ai-strip__hint { margin: 0; font-size: 13px; line-height: 1.5; color: var(--ui-muted); }
.ai-strip__actions { display: flex; align-items: center; gap: 8px; }
.ai-strip__label { font-size: 13px; color: var(--ui-muted); }
.ai-strip__toggle { min-width: 44px; min-height: 44px; border-radius: 10px; }
.ai-strip__toggle:focus-visible, .ai-strip__link:focus-visible { outline: 3px solid var(--ui-secondary); outline-offset: 2px; }
.ai-strip__link {
    display: inline-flex; align-items: center; min-height: 44px; padding: 0 14px; border-radius: 10px;
    font-size: 14px; font-weight: 600; color: #fff; background: var(--ui-secondary); touch-action: manipulation;
}
.ai-strip__link:hover { opacity: 0.92; }

.chat-header-icon-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}
</style>

