<template>
    <AppLayout>
        <UiPageHeader :title="$t('AI Assistant')" :subtitle="$t('Activate AI-generated responses in your conversations')">
            <template #actions>
                <span class="ai-chip" :class="featureActive ? 'ui-chip-success' : 'ui-chip-neutral'">
                    {{ featureActive ? $t('Active') : $t('Inactive') }}
                </span>
            </template>
        </UiPageHeader>

        <form @submit.prevent="submitForm()" class="mt-6 space-y-6">
            <UiSectionCard :title="$t('Feature status')" :subtitle="$t('Enable or disable this feature globally')">
                <template #icon>
                    <FormToggleSwitch v-model="form.is_active"/>
                </template>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <FormInput
                            v-model="form.settings.ai_global_api_key"
                            :name="$t('Global OpenAI API Key')"
                            :error="form.errors['settings.ai_global_api_key']"
                            :type="'password'"
                        />
                        <p class="ai-hint mt-2">{{ $t('Leave blank to keep the saved key') }}</p>
                    </div>

                    <FormSelect
                        v-model="form.settings.ai_key_policy"
                        :name="$t('AI key policy')"
                        :type="'text'"
                        :options="policyOptions"
                        :error="form.errors['settings.ai_key_policy']"
                        :class="'sm:col-span-2'"
                    />

                    <div class="ai-toggle-row sm:col-span-2">
                        <div class="min-w-0 flex-1">
                            <p class="ai-toggle-title">{{ $t('Allow organization API key override') }}</p>
                            <p class="ai-hint mt-1">{{ $t('Organizations can use their own AI key when the policy allows it') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.settings.ai_allow_org_override"/>
                    </div>
                </div>
            </UiSectionCard>

            <div class="flex justify-end">
                <button
                    type="submit"
                    class="ai-btn ai-btn--solid"
                    :disabled="form.processing"
                >
                    <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import { useI18n } from 'vue-i18n';
    import AppLayout from '../Layout/App.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const props = defineProps({
        config: {
            type: Array,
            required: true,
        },
        addon: {
            type: Object,
            required: true,
        },
    });

    const { t } = useI18n();

    const getValueByKey = (key, fallback = '') => {
        const found = props.config.find((item) => item.key === key);
        return found ? found.value : fallback;
    };

    const form = useForm({
        uuid: props.addon.uuid,
        settings: {
            ai_global_api_key: '',
            ai_key_policy: getValueByKey('ai_key_policy', 'hybrid') || 'hybrid',
            ai_allow_org_override: ['1', 1, true, 'true', 'on'].includes(getValueByKey('ai_allow_org_override', '1')),
        },
        is_active: Number(props.addon.is_active) === 1,
    });

    const featureActive = computed(() => form.is_active === true || form.is_active === 1);

    const policyOptions = [
        { value: 'hybrid', label: t('Hybrid') },
        { value: 'global_only', label: t('Global only') },
        { value: 'organization_only', label: t('Organization only') },
    ];

    const submitForm = () => {
        form.post('/admin/settings/features/ai-assistant', {
            preserveScroll: true,
        });
    };
</script>

<style scoped>
.ai-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.ai-hint {
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.ai-toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.85rem 1.05rem;
}

.ai-toggle-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.ai-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.6rem 1.3rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.ai-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.ai-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.ai-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
