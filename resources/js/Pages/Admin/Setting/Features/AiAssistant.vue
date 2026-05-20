<template>
    <AppLayout>
        <div class="max-w-5xl">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ $t('Features') }}</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-950">{{ $t('AI Assistant') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $t('Activate AI-generated responses in your conversations') }}</p>
                </div>
                <span :class="featureActive ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'" class="rounded-md px-3 py-2 text-sm font-medium">
                    {{ featureActive ? $t('Active') : $t('Inactive') }}
                </span>
            </div>

            <form @submit.prevent="submitForm()" class="space-y-8">
                <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">{{ $t('Feature status') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Enable or disable this feature globally') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.is_active"/>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <FormInput
                            v-model="form.settings.ai_global_api_key"
                            :name="$t('Global OpenAI API Key')"
                            :error="form.errors['settings.ai_global_api_key']"
                            :type="'password'"
                            :class="'md:col-span-2'"
                        />
                        <p class="mt-[-12px] text-xs text-slate-500 md:col-span-2">{{ $t('Leave blank to keep the saved key') }}</p>

                        <FormSelect
                            v-model="form.settings.ai_key_policy"
                            :name="$t('AI key policy')"
                            :type="'text'"
                            :options="policyOptions"
                            :error="form.errors['settings.ai_key_policy']"
                            :class="'md:col-span-2'"
                        />

                        <div class="rounded-md border border-slate-200 bg-slate-50 p-4 md:col-span-2">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-950">{{ $t('Allow organization API key override') }}</h3>
                                    <p class="mt-1 text-sm text-slate-500">{{ $t('Organizations can use their own AI key when the policy allows it') }}</p>
                                </div>
                                <FormToggleSwitch v-model="form.settings.ai_allow_org_override"/>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]"
                        :disabled="form.processing"
                    >
                        <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                        <span v-else>{{ $t('Save') }}</span>
                    </button>
                </div>
            </form>
        </div>
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
