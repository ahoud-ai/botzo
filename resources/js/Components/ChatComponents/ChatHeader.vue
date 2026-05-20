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

    const props = defineProps(['contact', 'displayContactInfo', 'ticketingIsEnabled', 'ticket', 'addon', 'activeConversationChannel']);

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
    const activeChannelLabel = computed(() => t('WhatsApp'));
    const showChannelBadge = computed(() => false);
    const contactDisplayName = computed(() => props.contact?.full_name || '');
    const contactAvatar = computed(() => props.contact?.avatar || '');
    const contactInitial = computed(() => (contactDisplayName.value || '?').substring(0, 1).toUpperCase());
    const contactSubtitle = computed(() => {
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
        'ai_assistant': props.contact?.ai_assistance_enabled,
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
        form3.post('/automation/contact/' + props.contact.uuid, {
            preserveState: true,
        });
    }
</script>
<template>
    <div class="h-20 bg-white border-b border-1 flex items-center justify-between px-4 md:px-4">
        <div class="flex items-center gap-x-4 cursor-pointer w-3/4">
            <Link :href="getBackUrl()" class="sm:block md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" width="2em" height="2em" viewBox="0 0 24 24"><path fill="currentColor" d="M19 11H7.14l3.63-4.36a1 1 0 1 0-1.54-1.28l-5 6a1.19 1.19 0 0 0-.09.15c0 .05 0 .08-.07.13A1 1 0 0 0 4 12a1 1 0 0 0 .07.36c0 .05 0 .08.07.13a1.19 1.19 0 0 0 .09.15l5 6A1 1 0 0 0 10 19a1 1 0 0 0 .64-.23a1 1 0 0 0 .13-1.41L7.14 13H19a1 1 0 0 0 0-2"/></svg>
            </Link>
            <div @click="toggleView">
                <img v-if="contactAvatar" class="rounded-full w-14 h-14 object-cover" :src="contactAvatar" alt="">
                <div v-else class="rounded-full w-10 h-10 flex items-center justify-center bg-slate-100">{{ contactInitial }}</div>
            </div>
            <div class="flex items-center w-full gap-x-8">
                <div>
                    <div class="flex items-center gap-2" @click="toggleView">
                        <span>{{ contactDisplayName }}</span>
                        <span v-if="showChannelBadge" class="rounded-md bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700">{{ activeChannelLabel }}</span>
                    </div>
                    <div class="w-full flex items-center text-xs">
                        <span @click="toggleView">{{ contactSubtitle }}</span>
                    </div>
                </div>
                <FormSelect v-if="canAssign" v-model="user" :name="''" :options="userOptions" :class="'col-span-1 md:block hidden'" :placeholder="$t('Select Agent')" @update:modelValue="changeTicketAgent()"/>
            </div>
        </div>
        <div>
            <div class="flex items-center gap-x-4">
                <button type="button" v-if="ticketState === 'open' && canChangeStatus" @click="changeTicketStatus('closed')" class="text-sm md:inline-flex hidden justify-center rounded-md border border-transparent bg-red-800 px-4 py-1 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">{{ $t('Mark as closed') }}</button>
                <button type="button" v-if="ticketState === 'closed' && canChangeStatus" @click="changeTicketStatus('open')" class="text-sm md:inline-flex hidden justify-center rounded-md border border-transparent bg-primary px-4 py-1 text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2">{{ $t('Mark as open') }}</button>

                <Dropdown v-if="!displayContact">
                    <button type="button" class="inline-flex w-full justify-center items-center rounded-md text-sm font-medium text-black hover:bg-opacity-30 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75">
                        <span class="bg-slate-200 p-1 rounded-full cursor-pointer flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.75a.75.75 0 1 1 0-1.5a.75.75 0 0 1 0 1.5Zm0 6a.75.75 0 1 1 0-1.5a.75.75 0 0 1 0 1.5Zm0 6a.75.75 0 1 1 0-1.5a.75.75 0 0 1 0 1.5Z"/></svg>
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
                <div v-else @click="toggleView" class="bg-slate-200 p-2 rounded-lg text-sm cursor-pointer">
                    <span>{{ $t('Back') }}</span>
                </div>
            </div>
        </div>
    </div>
    <div v-if="addon == 1 && canManageAiAssistant" class="flex justify-between items-center bg-white border-b border-1 py-2 px-4 md:px-4">
        <div class="text-sm py-1 px-3 rounded-md flex items-center gap-x-2 w-[fit-content]" :class="form3.ai_assistant ? 'bg-green-50' : 'bg-red-50'">
            <span :class="form3.ai_assistant ? 'text-green-500' : 'text-red-500'">
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24"><path fill="currentColor" d="M12 22q-2.075 0-3.9-.788t-3.175-2.137T2.788 15.9T2 12t.788-3.9t2.137-3.175T8.1 2.788T12 2t3.9.788t3.175 2.137T21.213 8.1T22 12t-.788 3.9t-2.137 3.175t-3.175 2.138T12 22"/></svg>
            </span>
            <span>{{ $t('AI Assistant') }}</span>
        </div>
        <div class="flex items-center gap-x-2">
            <span class="text-[13px]">{{ $t('Enable/disable AI assistant') }}</span>
            <FormToggleSwitch v-model="form3.ai_assistant" @update:modelValue="submitForm3" />
        </div>
    </div>

    <Modal :label="$t('Add Note')" :isOpen="isOpenModal" @close="isOpenModal = false">
        <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
            <form @submit.prevent="submitForm()" class="">
                <FormTextArea v-model="form2.notes" :error="form2.errors.note" :name="''" :class="'col-span-2'"/>
                <div class="mt-4 flex">
                    <button type="button" @click="isOpenModal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 me-4">{{ $t('Cancel') }}</button>
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

