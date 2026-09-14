<template>
    <SettingLayout :modules="props.modules" :title="$t('Ticket settings')" :subtitle="$t('Configure how support tickets are handled')">
        <div class="min-h-0">
            <div class="flex justify-center items-center">
                <div class="w-full md:w-[60em]">
                    <UiSectionCard :title="$t('Enable ticketing')" :subtitle="$t('Activate ticketing workflow in your conversations')" class="mb-4">
                        <template #icon>
                            <FormToggleSwitch v-model="form.active" @update:modelValue="toggleState1" />
                        </template>
                    </UiSectionCard>

                    <UiSectionCard v-if="form.active" :title="$t('Auto assignment')" :subtitle="$t('Use auto-assignment rules to evenly distribute chats among agents automatically.')" class="mb-4">
                        <div class="p-5">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <button type="button" @click="toggleAutoAssignment(false)" class="settings-choice-card" :class="{ 'settings-choice-card--active': form.auto_assignment === false }">
                                    <span class="settings-choice-check">
                                        <svg v-if="form.auto_assignment === false" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    </span>
                                    <span class="text-start">
                                        <div class="settings-field-label">{{ $t('Off') }}</div>
                                        <div class="settings-field-hint">{{ $t('Team members pick conversations manually from Unassigned folder.') }}</div>
                                    </span>
                                </button>
                                <button type="button" @click="toggleAutoAssignment(true)" class="settings-choice-card" :class="{ 'settings-choice-card--active': form.auto_assignment === true }">
                                    <span class="settings-choice-check">
                                        <svg v-if="form.auto_assignment === true" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path></svg>
                                    </span>
                                    <span class="text-start">
                                        <div class="settings-field-label">{{ $t('Auto') }}</div>
                                        <div class="settings-field-hint">{{ $t('Distribute conversations among all your available team members.') }}</div>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </UiSectionCard>

                    <UiSectionCard v-if="form.active" :title="$t('Reassign chats that have been reopened')" :subtitle="$t('Enable this option to reassign chats when a contact re-opens a closed conversation. If disabled, reopened chats will either return to the previous agent or remain unassigned, based on auto-assignment settings.')" class="mb-4">
                        <template #icon>
                            <FormToggleSwitch v-model="form.reassign_reopened_chats" @update:modelValue="toggleState2" />
                        </template>
                    </UiSectionCard>

                    <UiSectionCard v-if="form.active" :title="$t('Grant agents access to view all chats not assigned to them')" :subtitle="$t('Disable this option, if you want live chat agents to have access only to new conversations and conversations that are assigned to them.')" class="mb-20">
                        <template #icon>
                            <FormToggleSwitch v-model="form.allow_agents_to_view_all_chats" @update:modelValue="toggleState3" />
                        </template>
                    </UiSectionCard>
                </div>
            </div>
        </div>
    </SettingLayout>
</template>
<script setup>
    import SettingLayout from "./Layout.vue";
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';
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

<style scoped>
.settings-field-label {
    font-size: 0.98rem;
    font-weight: 700;
    color: var(--ui-text);
}

.settings-field-hint {
    margin-top: 0.25rem;
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.settings-choice-card {
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    border-radius: 1rem;
    border: 1.5px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.1rem;
    text-align: start;
    transition: border-color 160ms ease, background-color 160ms ease;
}

.settings-choice-card:hover {
    background: var(--ui-surface-soft);
}

.settings-choice-card--active {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
}

.settings-choice-check {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 1.3rem;
    height: 1.3rem;
    margin-top: 0.15rem;
    border-radius: 0.4rem;
    border: 1.5px solid var(--ui-border-strong);
    background: transparent;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.settings-choice-card--active .settings-choice-check {
    background: var(--ui-secondary);
    border-color: var(--ui-secondary);
}

</style>
