<template>
    <SettingLayout :modules="props.modules">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="md:w-[60em]">
                    <div class="bg-white border border-slate-200 rounded-lg pt-2 text-sm mb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-4 mt-2">
                            <div class="flex w-full">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Enable ticketing') }}</h4>
                                    <div class="mb-1 text-slate-500">{{ $t('Activate ticketing workflow in your conversations') }}</div>
                                </div>
                                <div class="ms-auto">
                                    <FormToggleSwitch v-model="form.active" @update:modelValue="toggleState1" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.active" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-2 mt-2">
                            <div class="flex w-full mb-4">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Auto assignment') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Use auto-assignment rules to evenly distribute chats among agents automatically.') }}
                                    </span> 
                                </div>
                            </div>
                            <div class="w-5/5">
                                <div class="grid grid-cols-2 gap-x-4">
                                    <div class="border rounded-xl p-4">
                                        <div class="flex gap-x-2">
                                            <div>
                                                <div class="flex mt-[1px]">
                                                    <label @click="toggleAutoAssignment(false)" for="myCheckbox" class="cursor-pointer">
                                                        <div class="w-4 h-4 border border-gray-400 rounded-md flex items-center justify-center" :class="form.auto_assignment === false ? 'bg-[color:var(--ui-text)]' : ''">
                                                            <svg v-if="form.auto_assignment === false" class="w-4 h-4" :class="form.auto_assignment === false ? 'text-white' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            <div @click="toggleAutoAssignment(false)" class="cursor-pointer">
                                                <div>{{ $t('Off') }}</div>
                                                <div>{{ $t('Team members pick conversations manually from Unassigned folder.') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="border rounded-xl p-4">
                                        <div class="flex gap-x-2">
                                            <div>
                                                <div class="flex mt-[1px]">
                                                    <label @click="toggleAutoAssignment(true)" for="myCheckbox" class="cursor-pointer">
                                                        <div class="w-4 h-4 border border-gray-400 rounded-md flex items-center justify-center" :class="form.auto_assignment === true ? 'bg-[color:var(--ui-text)]' : ''">
                                                            <svg v-if="form.auto_assignment === true" class="w-4 h-4" :class="form.auto_assignment === true ? 'text-white' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                            </svg>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                            <div @click="toggleAutoAssignment(true)" class="cursor-pointer">
                                                <div>{{ $t('Auto') }}</div>
                                                <div>{{ $t('Distribute conversations among all your available team members.') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.active" class="bg-white border border-slate-200 rounded-lg pt-2 text-sm mb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-4 mt-2">
                            <div class="flex w-full">
                                <div class="w-3/4 text-md">
                                    <h4 class="text-[16px]">{{ $t('Reassign chats that have been reopened') }}</h4>
                                    <div class="mb-1 text-slate-500">{{ $t('Enable this option to reassign chats when a contact re-opens a closed conversation. If disabled, reopened chats will either return to the previous agent or remain unassigned, based on auto-assignment settings.') }}</div>
                                </div>
                                <div class="w-1/4">
                                    <div class="ms-auto">
                                        <FormToggleSwitch v-model="form.reassign_reopened_chats" @update:modelValue="toggleState2" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="form.active" class="bg-white border border-slate-200 rounded-lg pt-2 text-sm mb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-4 mt-2">
                            <div class="flex w-full">
                                <div class="w-3/4 text-md">
                                    <h4 class="text-[16px]">{{ $t('Grant agents access to view all chats not assigned to them') }}</h4>
                                    <div class="mb-1 text-slate-500">{{ $t('Disable this option, if you want live chat agents to have access only to new conversations and conversations that are assigned to them.') }}</div>
                                </div>
                                <div class="w-1/4">
                                    <div class="ms-auto">
                                        <FormToggleSwitch v-model="form.allow_agents_to_view_all_chats" @update:modelValue="toggleState3" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import { ref, watch } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps(['rows', 'filters', 'settings', 'modules']);
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);

    const form = useForm({
        active: settings.value?.tickets?.active ?? false,
        auto_assignment: settings.value?.tickets?.auto_assignment ?? false,
        reassign_reopened_chats: settings.value?.tickets?.reassign_reopened_chats ?? false,
        allow_agents_to_view_all_chats: settings.value?.tickets?.allow_agents_to_view_all_chats ?? false,
    });

    const toggleState1 = () => {
        submitForm();
    }

    const toggleState2 = () => {
        submitForm();
    }

    const toggleState3 = () => {
        submitForm();
    }

    const toggleAutoAssignment = (el) => {
        form.auto_assignment = el;
        submitForm();
    }

    const submitForm = async () => {
        form.post('/settings/tickets', {
            preserveScroll: true,
        })
    };
</script>


