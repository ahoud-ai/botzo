<template>
    <AppLayout>
        <div class="max-w-5xl">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold text-primary">{{ $t('Features') }}</p>
                    <h2 class="mt-1 text-2xl font-semibold text-slate-950">{{ $t('Embedded Signup') }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $t('Configure embedded signup global settings for WhatsApp onboarding.') }}</p>
                </div>
                <span :class="featureActive ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600'" class="rounded-md px-3 py-2 text-sm font-medium">
                    {{ featureActive ? $t('Active') : $t('Inactive') }}
                </span>
            </div>

            <form @submit.prevent="submitForm()" enctype="multipart/form-data" class="space-y-8">
                <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">{{ $t('Feature status') }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $t('Use this switch to allow or block Embedded Signup globally.') }}</p>
                        </div>
                        <FormToggleSwitch v-model="form.is_active"/>
                    </div>

                    <div class="grid gap-5 md:grid-cols-2">
                        <FormInput v-model="form.settings.whatsapp_client_id" :name="$t('App ID')" :error="form.errors['settings.whatsapp_client_id']" :type="'text'" :class="'md:col-span-1'"/>
                        <FormInput v-model="form.settings.whatsapp_client_secret" :name="$t('App secret')" :error="form.errors['settings.whatsapp_client_secret']" :type="'password'" :class="'md:col-span-1'"/>
                        <FormInput v-model="form.settings.whatsapp_config_id" :name="$t('Config ID')" :error="form.errors['settings.whatsapp_config_id']" :type="'text'" :class="'md:col-span-2'"/>
                        <FormInput v-model="form.settings.whatsapp_access_token" :name="$t('Access token')" :error="form.errors['settings.whatsapp_access_token']" :type="'password'" :class="'md:col-span-2'"/>
                    </div>
                </section>

                <section class="rounded-md border border-amber-200 bg-amber-50 p-5 shadow-sm">
                    <div class="mb-3 flex items-center justify-between border-b border-amber-200 pb-3">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">{{ $t('Embedded signup readiness') }}</h3>
                            <p class="mt-1 text-sm text-slate-600">{{ $t('Webhook setup') }}</p>
                        </div>
                        <button type="button" class="text-sm font-medium text-primary underline" @click="fetchEmbeddedSignupHealth">{{ $t('Refresh') }}</button>
                    </div>

                    <div v-if="embeddedSignupHealthLoading" class="text-sm text-slate-600">{{ $t('Loading...') }}</div>
                    <div v-else-if="embeddedSignupHealth" class="mb-4 space-y-3 text-sm text-slate-700">
                        <div class="font-semibold">
                            <span class="me-1">{{ $t('Overall status') }}:</span>
                            <span :class="embeddedSignupHealth.is_ready ? 'text-green-700' : 'text-red-700'">
                                {{ embeddedSignupHealth.is_ready ? $t('Ready') : $t('Not ready') }}
                            </span>
                        </div>
                        <div class="grid gap-2 md:grid-cols-3">
                            <div class="rounded-md border border-amber-200 bg-white px-3 py-2">{{ $t('App ID') }}: <strong>{{ embeddedSignupHealth.checks?.app_id ? $t('OK') : $t('Missing') }}</strong></div>
                            <div class="rounded-md border border-amber-200 bg-white px-3 py-2">{{ $t('App secret') }}: <strong>{{ embeddedSignupHealth.checks?.app_secret ? $t('OK') : $t('Missing') }}</strong></div>
                            <div class="rounded-md border border-amber-200 bg-white px-3 py-2">{{ $t('Config ID') }}: <strong>{{ embeddedSignupHealth.checks?.config_id ? $t('OK') : $t('Missing') }}</strong></div>
                            <div class="rounded-md border border-amber-200 bg-white px-3 py-2">{{ $t('Access token') }}: <strong>{{ embeddedSignupHealth.checks?.fallback_token ? $t('OK') : $t('Missing') }}</strong></div>
                            <div class="rounded-md border border-amber-200 bg-white px-3 py-2">{{ $t('Verify token') }}: <strong>{{ embeddedSignupHealth.checks?.verify_token ? $t('OK') : $t('Missing') }}</strong></div>
                            <div class="rounded-md border border-amber-200 bg-white px-3 py-2">{{ $t('Feature') }}: <strong>{{ embeddedSignupHealth.checks?.addon_active ? $t('OK') : $t('Missing') }}</strong></div>
                        </div>
                    </div>

                    <div class="space-y-1 text-sm text-slate-700">
                        <p class="break-all">{{ $t('Callback URL') }}: {{ currentURL + '/webhook/waba' }}</p>
                        <p class="break-all">{{ $t('Verify token') }}: {{ getValueByKey('whatsapp_callback_token') }}</p>
                    </div>
                </section>

                <section class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">{{ $t('Meta review tests') }}</h3>
                            <p class="mt-1 text-sm leading-6 text-slate-600">{{ $t('Run the required Meta App Review API calls from the saved system user token directly on the server.') }}</p>
                        </div>
                        <button
                            type="button"
                            class="inline-flex items-center rounded-md bg-primary px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
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
                            <span :class="{
                                'text-green-700': metaReviewReport.status === 'passed',
                                'text-amber-700': metaReviewReport.status === 'warning',
                                'text-red-700': metaReviewReport.status === 'failed',
                                'text-slate-500': metaReviewReport.status === 'skipped',
                            }">
                                {{ metaReviewReport.status === 'passed'
                                    ? $t('Passed')
                                    : (metaReviewReport.status === 'warning'
                                        ? $t('Needs attention')
                                        : (metaReviewReport.status === 'failed' ? $t('Failed') : $t('Skipped'))) }}
                            </span>
                            <span v-if="metaReviewReport.timestamp" class="text-slate-500">
                                {{ $t('Last run') }}: {{ metaReviewReport.timestamp }}
                            </span>
                        </div>

                        <div v-if="metaReviewReport.summary" class="grid grid-cols-2 gap-2 text-sm md:grid-cols-4">
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-2">{{ $t('Passed') }}: <strong>{{ metaReviewReport.summary.passed }}</strong></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-2">{{ $t('Warnings') }}: <strong>{{ metaReviewReport.summary.warning }}</strong></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-2">{{ $t('Failed') }}: <strong>{{ metaReviewReport.summary.failed }}</strong></div>
                            <div class="rounded-md border border-slate-200 bg-slate-50 p-2">{{ $t('Skipped') }}: <strong>{{ metaReviewReport.summary.skipped }}</strong></div>
                        </div>

                        <div v-if="metaReviewReport.message" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            {{ $t(metaReviewReport.message) }}
                        </div>

                        <div class="space-y-2">
                            <div v-for="test in metaReviewReport.tests || []" :key="test.key" class="rounded-md border border-slate-200 bg-white p-3 text-sm">
                                <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-semibold text-slate-900">{{ test.label }}</div>
                                    <span :class="{
                                        'text-green-700': test.status === 'passed',
                                        'text-amber-700': test.status === 'warning',
                                        'text-red-700': test.status === 'failed',
                                        'text-slate-500': test.status === 'skipped',
                                    }">
                                        {{ test.status === 'passed'
                                            ? $t('Passed')
                                            : (test.status === 'warning'
                                                ? $t('Needs attention')
                                                : (test.status === 'failed' ? $t('Failed') : $t('Skipped'))) }}
                                    </span>
                                </div>
                                <p class="mb-1 break-all text-slate-600">{{ test.endpoint }}</p>
                                <p class="text-slate-700">{{ test.message }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="text-sm text-slate-500">
                        {{ $t('Run the server-side tests once so you can attach the results to Meta App Review.') }}
                    </div>
                </section>

                <div class="flex justify-end">
                    <button type="submit"
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
    import axios from 'axios';
    import { computed, onMounted, ref } from 'vue';
    import { useForm } from "@inertiajs/vue3";
    import AppLayout from "./Layout/App.vue";
    import FormInput from '@/Components/FormInput.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';

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
