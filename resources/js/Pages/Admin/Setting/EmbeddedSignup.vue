<template>
    <AppLayout>
        <UiPageHeader :title="$t('Embedded Signup')" :subtitle="$t('Configure embedded signup global settings for WhatsApp onboarding.')">
            <template #actions>
                <span class="es-chip" :class="featureActive ? 'ui-chip-success' : 'ui-chip-neutral'">
                    {{ featureActive ? $t('Active') : $t('Inactive') }}
                </span>
            </template>
        </UiPageHeader>

        <form @submit.prevent="submitForm()" enctype="multipart/form-data" class="mt-6 space-y-6">
            <UiSectionCard :title="$t('Feature status')" :subtitle="$t('Use this switch to allow or block Embedded Signup globally.')">
                <template #icon>
                    <FormToggleSwitch v-model="form.is_active"/>
                </template>

                <div class="grid gap-5 sm:grid-cols-2">
                    <FormInput v-model="form.settings.whatsapp_client_id" :name="$t('App ID')" :error="form.errors['settings.whatsapp_client_id']" :type="'text'"/>
                    <FormInput v-model="form.settings.whatsapp_client_secret" :name="$t('App secret')" :error="form.errors['settings.whatsapp_client_secret']" :type="'password'"/>
                    <FormInput v-model="form.settings.whatsapp_config_id" :name="$t('Config ID')" :error="form.errors['settings.whatsapp_config_id']" :type="'text'" :class="'sm:col-span-2'"/>
                    <FormInput v-model="form.settings.whatsapp_access_token" :name="$t('Access token')" :error="form.errors['settings.whatsapp_access_token']" :type="'password'" :class="'sm:col-span-2'"/>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Embedded signup readiness')" :subtitle="$t('Webhook setup')">
                <div class="mb-4 flex justify-end">
                    <button type="button" class="es-btn es-btn--ghost" @click="fetchEmbeddedSignupHealth">{{ $t('Refresh') }}</button>
                </div>

                <div v-if="embeddedSignupHealthLoading" class="es-muted text-sm">{{ $t('Loading...') }}</div>
                <div v-else-if="embeddedSignupHealth" class="mb-4 space-y-3">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="font-semibold">{{ $t('Overall status') }}:</span>
                        <span class="es-chip" :class="embeddedSignupHealth.is_ready ? 'ui-chip-success' : 'ui-chip-danger'">
                            {{ embeddedSignupHealth.is_ready ? $t('Ready') : $t('Not ready') }}
                        </span>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="es-tile">{{ $t('App ID') }}: <span class="es-chip" :class="embeddedSignupHealth.checks?.app_id ? 'ui-chip-success' : 'ui-chip-danger'">{{ embeddedSignupHealth.checks?.app_id ? $t('OK') : $t('Missing') }}</span></div>
                        <div class="es-tile">{{ $t('App secret') }}: <span class="es-chip" :class="embeddedSignupHealth.checks?.app_secret ? 'ui-chip-success' : 'ui-chip-danger'">{{ embeddedSignupHealth.checks?.app_secret ? $t('OK') : $t('Missing') }}</span></div>
                        <div class="es-tile">{{ $t('Config ID') }}: <span class="es-chip" :class="embeddedSignupHealth.checks?.config_id ? 'ui-chip-success' : 'ui-chip-danger'">{{ embeddedSignupHealth.checks?.config_id ? $t('OK') : $t('Missing') }}</span></div>
                        <div class="es-tile">{{ $t('Access token') }}: <span class="es-chip" :class="embeddedSignupHealth.checks?.fallback_token ? 'ui-chip-success' : 'ui-chip-danger'">{{ embeddedSignupHealth.checks?.fallback_token ? $t('OK') : $t('Missing') }}</span></div>
                        <div class="es-tile">{{ $t('Verify token') }}: <span class="es-chip" :class="embeddedSignupHealth.checks?.verify_token ? 'ui-chip-success' : 'ui-chip-danger'">{{ embeddedSignupHealth.checks?.verify_token ? $t('OK') : $t('Missing') }}</span></div>
                        <div class="es-tile">{{ $t('Feature') }}: <span class="es-chip" :class="embeddedSignupHealth.checks?.addon_active ? 'ui-chip-success' : 'ui-chip-danger'">{{ embeddedSignupHealth.checks?.addon_active ? $t('OK') : $t('Missing') }}</span></div>
                    </div>
                </div>

                <div class="es-banner es-banner--info space-y-1">
                    <p class="break-all">{{ $t('Callback URL') }}: {{ currentURL + '/webhook/waba' }}</p>
                    <p class="break-all">{{ $t('Verify token') }}: {{ getValueByKey('whatsapp_callback_token') }}</p>
                </div>
            </UiSectionCard>

            <UiSectionCard :title="$t('Meta review tests')" :subtitle="$t('Run the required Meta App Review API calls from the saved system user token directly on the server.')">
                <div class="mb-4 flex justify-end">
                    <button
                        type="button"
                        class="es-btn es-btn--solid"
                        :disabled="metaReviewLoading"
                        @click="runMetaReviewTests"
                    >
                        <span v-if="metaReviewLoading">{{ $t('Running tests...') }}</span>
                        <span v-else>{{ $t('Run Meta review tests') }}</span>
                    </button>
                </div>

                <div v-if="metaReviewReport" class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="font-semibold">{{ $t('Overall status') }}:</span>
                        <span class="es-chip" :class="testStatusChip(metaReviewReport.status)">
                            {{ testStatusLabel(metaReviewReport.status) }}
                        </span>
                        <span v-if="metaReviewReport.timestamp" class="es-muted">
                            {{ $t('Last run') }}: {{ metaReviewReport.timestamp }}
                        </span>
                    </div>

                    <div v-if="metaReviewReport.summary" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="es-tile">{{ $t('Passed') }}: <strong>{{ metaReviewReport.summary.passed }}</strong></div>
                        <div class="es-tile">{{ $t('Warnings') }}: <strong>{{ metaReviewReport.summary.warning }}</strong></div>
                        <div class="es-tile">{{ $t('Failed') }}: <strong>{{ metaReviewReport.summary.failed }}</strong></div>
                        <div class="es-tile">{{ $t('Skipped') }}: <strong>{{ metaReviewReport.summary.skipped }}</strong></div>
                    </div>

                    <div v-if="metaReviewReport.message" class="es-banner es-banner--danger">
                        {{ $t(metaReviewReport.message) }}
                    </div>

                    <div class="space-y-2">
                        <div v-for="test in metaReviewReport.tests || []" :key="test.key" class="es-tile es-tile--block">
                            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                <div class="font-semibold">{{ test.label }}</div>
                                <span class="es-chip" :class="testStatusChip(test.status)">
                                    {{ testStatusLabel(test.status) }}
                                </span>
                            </div>
                            <p class="es-muted mb-1 break-all text-xs">{{ test.endpoint }}</p>
                            <p class="text-sm">{{ test.message }}</p>
                        </div>
                    </div>
                </div>

                <div v-else class="es-muted text-sm">
                    {{ $t('Run the server-side tests once so you can attach the results to Meta App Review.') }}
                </div>
            </UiSectionCard>

            <div class="flex justify-end">
                <button type="submit" class="es-btn es-btn--solid" :disabled="form.processing">
                    <svg v-if="form.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                    <span v-else>{{ $t('Save') }}</span>
                </button>
            </div>
        </form>
    </AppLayout>
</template>

<script setup>
    import axios from 'axios';
    import { computed, onMounted, ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import AppLayout from "./Layout/App.vue";
    import FormInput from '@/Components/FormInput.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import UiSectionCard from '@/Components/UI/UiSectionCard.vue';

    const { t } = useI18n();

    const props = defineProps({
        config: {
            type: Array,
            required: true
        },
        addon: {
            type: Object,
            required: true,
        },
    });

    const currentURL = ref(window.location.origin);
    const embeddedSignupHealth = ref(null);
    const embeddedSignupHealthLoading = ref(false);
    const metaReviewReport = ref(null);
    const metaReviewLoading = ref(false);

    const getValueByKey = (key) => {
        const found = props.config.find((item) => item.key === key);
        return found ? found.value : '';
    };

    const form = useForm({
        uuid: props.addon.uuid,
        settings: {
            whatsapp_client_id: getValueByKey('whatsapp_client_id'),
            whatsapp_client_secret: getValueByKey('whatsapp_client_secret'),
            whatsapp_config_id: getValueByKey('whatsapp_config_id'),
            whatsapp_access_token: getValueByKey('whatsapp_access_token'),
        },
        is_active: Number(props.addon.is_active) === 1 || getValueByKey('is_embedded_signup_active') == 1,
    });

    const featureActive = computed(() => form.is_active === true || form.is_active === 1);

    const testStatusChip = (status) => {
        switch (status) {
            case 'passed':
                return 'ui-chip-success';
            case 'warning':
                return 'ui-chip-warning';
            case 'failed':
                return 'ui-chip-danger';
            default:
                return 'ui-chip-neutral';
        }
    };

    const testStatusLabel = (status) => {
        switch (status) {
            case 'passed':
                return t('Passed');
            case 'warning':
                return t('Needs attention');
            case 'failed':
                return t('Failed');
            default:
                return t('Skipped');
        }
    };

    const fetchEmbeddedSignupHealth = async () => {
        embeddedSignupHealthLoading.value = true;
        try {
            const response = await axios.get('/admin/settings/features/embedded-signup/health');
            embeddedSignupHealth.value = response.data;
        } catch (error) {
            embeddedSignupHealth.value = null;
        } finally {
            embeddedSignupHealthLoading.value = false;
        }
    };

    const runMetaReviewTests = async () => {
        metaReviewLoading.value = true;
        try {
            const response = await axios.post('/admin/settings/features/embedded-signup/meta-review-tests');
            metaReviewReport.value = response.data;
        } catch (error) {
            metaReviewReport.value = {
                status: 'failed',
                message: error?.response?.data?.message || 'Unable to run Meta review tests.',
                tests: [],
                summary: null,
            };
        } finally {
            metaReviewLoading.value = false;
        }
    };

    const submitForm = async () => {
        form.post('/admin/settings/features/embedded-signup', {
            preserveScroll: true,
            onSuccess: () => {
                fetchEmbeddedSignupHealth();
            },
        });
    };

    onMounted(() => {
        fetchEmbeddedSignupHealth();
    });
</script>

<style scoped>
.es-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.3rem 0.75rem;
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
}

.es-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.85rem;
    padding: 0.55rem 1.1rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease, background-color 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.es-btn--ghost {
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
}

.es-btn--ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.es-btn--solid {
    border: 1px solid transparent;
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: var(--ui-shadow-1);
}

.es-btn--solid:hover:not(:disabled) {
    filter: brightness(1.05);
}

.es-btn--solid:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.es-tile {
    border-radius: 0.8rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.6rem 0.8rem;
    font-size: 0.85rem;
    color: var(--ui-text);
}

.es-tile--block {
    background: var(--ui-surface);
    padding: 0.85rem 1rem;
}

.es-muted {
    color: var(--ui-muted);
}

.es-banner {
    border-radius: 0.85rem;
    border: 1px solid;
    padding: 0.85rem 1rem;
    font-size: 0.85rem;
}

.es-banner--info {
    border-color: color-mix(in srgb, var(--ui-primary) 30%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-primary) 8%, var(--ui-surface));
    color: var(--ui-text);
}

.es-banner--danger {
    border-color: color-mix(in srgb, var(--ui-danger) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-danger) 10%, var(--ui-surface));
    color: var(--ui-text);
}
</style>
