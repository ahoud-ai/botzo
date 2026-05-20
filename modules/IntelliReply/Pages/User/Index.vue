<template>
    <SettingLayout :aimodule="aimodule" :fbmodule="fbmodule">
        <div class="md:h-[90vh]">
            <div class="flex justify-center items-center">
                <div class="md:w-[60em]">
                    <div class="bg-white border border-slate-200 rounded-lg pt-2 text-sm mb-4 px-4 mb-20">
                        <div class="w-full py-2 mb-4 mt-2">
                            <div class="flex w-full">
                                <div class="text-md">
                                    <h4 class="text-[16px]">{{ $t('Enable AI Assistant') }}</h4>
                                    <div class="mb-1 text-slate-500">{{ $t('Activate AI-generated responses in your conversations') }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $t('Key policy') }}: {{ keyPolicyLabel }} | {{ $t('Current key source') }}: {{ effectiveKeySourceLabel }}
                                    </div>
                                </div>
                                <div class="ml-auto">
                                    <div class="flex items-center gap-x-3">
                                        <FormToggleSwitch
                                            v-if="aiConfigured"
                                            :modelValue="form.active"
                                            @update:modelValue="handleActivationToggle"
                                        />
                                        <FormToggleSwitch
                                            v-else
                                            :modelValue="form2.active"
                                            @update:modelValue="handleSetupToggle"
                                        />

                                        <div v-if="aiConfigured">
                                            |
                                        </div>
                                        <button v-if="aiConfigured" @click="isOpenFormModal = true" class="bg-primary text-white h-8 rounded-lg text-[13px] px-3 w-fit">
                                            {{ $t('Update') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="aiUsageNotices.length || aiUsageMetrics.length" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 px-4">
                        <div class="w-full py-2 mb-3 mt-2">
                            <h4 class="text-[16px]">{{ $t('AI usage overview') }}</h4>
                            <div class="text-slate-500">{{ $t('This snapshot tracks only AI requests billed to the global AI key.') }}</div>
                        </div>

                        <div v-if="aiUsageNotices.length" class="space-y-3 pb-4">
                            <div
                                v-for="notice in aiUsageNotices"
                                :key="notice.key"
                                class="rounded-lg border px-4 py-3"
                                :class="noticeClasses(notice)"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="noticeIconClasses(notice)" v-html="noticeIconSvg(notice)"></span>
                                    <div>
                                        <p class="font-semibold">{{ notice.title }}</p>
                                        <p class="mt-1 text-xs leading-6">{{ notice.message }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="aiUsageMetrics.length" class="grid gap-3 md:grid-cols-3 pb-3">
                            <article
                                v-for="metric in aiUsageMetrics"
                                :key="metric.key"
                                class="rounded-lg border p-4"
                                :class="metricCardClasses(metric)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ metric.label }}</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ metric.helper }}</p>
                                    </div>
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white text-primary shadow-sm" v-html="metricIcon(metric.key)"></span>
                                </div>
                                <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
                                    <span>{{ $t('Used') }}: {{ metric.used }}</span>
                                    <span>{{ $t('Limit') }}: {{ formatUsageLimit(metric.limit) }}</span>
                                </div>
                                <div v-if="metric.limit >= 0" class="mt-3">
                                    <div class="mb-2 flex items-center justify-between text-xs text-slate-500">
                                        <span>{{ $t('Usage') }}</span>
                                        <span>{{ metric.percentage ?? 0 }}%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                        <div class="h-full rounded-full transition-all duration-500" :class="metricProgressClasses(metric)" :style="{ width: `${metric.percentage ?? 0}%` }"></div>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <form @submit.prevent="submitForm3()" v-if="aiConfigured" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-4 pb-4">
                        <div class="flex items-center justify-between px-4 pt-2 pb-4">
                            <div @click="toggleSetupForm()" class="w-[90%] cursor-pointer">
                                <h4 class="text-[16px]">{{ $t('AI Assistant Setup') }}</h4>
                                <div class="text-slate-500">{{ $t('Setup keywords for AI assistance') }}</div>
                            </div>
                            <div class="w-[10%]">
                                <button type="button" @click="toggleSetupForm()" class="hover:bg-slate-50 rounded-full p-1 float-right">
                                    <svg v-if="setupForm" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="black" fill-rule="evenodd" d="M16.53 14.03a.75.75 0 0 1-1.06 0L12 10.56l-3.47 3.47a.75.75 0 0 1-1.06-1.06l4-4a.75.75 0 0 1 1.06 0l4 4a.75.75 0 0 1 0 1.06" clip-rule="evenodd"/></svg>
                                    <svg v-if="!setupForm" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="black" fill-rule="evenodd" d="M16.53 8.97a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 0 1-1.06 0l-4-4a.75.75 0 1 1 1.06-1.06L12 12.44l3.47-3.47a.75.75 0 0 1 1.06 0" clip-rule="evenodd"/></svg>
                                </button>
                            </div>
                        </div>
                        <div v-if="setupForm">
                            <div class="flex space-x-10 border-b w-full px-4 py-6">
                                <div class="w-[70%]">
                                    <span class="text-slate-600">{{ $t('Enable automatic AI assistance for new conversations') }}</span>
                                    <div class="text-xs text-slate-700 flex items-center">
                                        <span>{{ $t('Turn on this option to let users automatically get help from the AI whenever they start a new conversation or ticket. If enabled, this will override the keywords set for initiating AI chat.') }}</span>
                                    </div>
                                </div>
                                <div class="w-[30%]">
                                    <div class="ml-auto flex justify-end">
                                        <FormToggleSwitch
                                            :modelValue="form3.enable_automatic_responses"
                                            @update:modelValue="value => form3.enable_automatic_responses = value"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="flex space-x-10 border-b w-full px-4 py-6">
                                <div class="w-[40%]">
                                    <span class="text-slate-600">{{ $t('Keyword(s) to start the AI agent') }}</span>
                                    <div class="text-xs text-slate-700 flex items-center">
                                        <span>{{ $t('Set word or phrase') }}</span>
                                    </div>
                                </div>
                                <div class="w-[60%]">
                                    <FormInput v-model="form3.start_keywords" :error="form3.errors.start_keywords" :name="''" :type="'text'" :class="'col-span-4'"/>
                                </div>
                            </div>
                            <div class="flex space-x-10 border-b w-full px-4 py-6">
                                <div class="w-[40%]">
                                    <span class="text-slate-600">{{ $t('Keyword(s) to stop the AI agent') }}</span>
                                    <div class="text-xs text-slate-700 flex items-center">
                                        <span>{{ $t('Set word or phrase') }}</span>
                                    </div>
                                </div>
                                <div class="w-[60%]">
                                    <FormTextArea v-model="form3.stop_keywords" :error="form3.errors.stop_keywords" :name="''" :type="'text'" :class="'col-span-4'"/>
                                </div>
                            </div>
                            <div class="flex px-4 pt-1">
                                <div class="ml-auto mt-2">
                                    <button type="submit" class="float-right bg-primary text-white h-8 rounded-lg text-[13px] px-3 w-fit" :disabled="form3.processing">
                                        <svg v-if="form3.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                                        <span v-else>{{ $t('Save') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    <div v-if="aiConfigured" class="bg-white border border-slate-200 rounded-lg py-2 text-sm mb-20 pb-4 px-4">
                        <div class="w-full py-2 mb-4 mt-2">
                            <div class="flex w-full mb-4">
                                <div class="text-md w-[70%]">
                                    <h4 class="text-[16px]">{{ $t('Knowledge Base') }}</h4>
                                    <span class="flex items-center mt-1 text-slate-500">
                                        {{ $t('Enhance your AI assistant by uploading information to improve client interactions.') }}
                                    </span> 
                                </div>
                                <div class="ml-auto w-[40%]">
                                    <div class="float-right flex items-center gap-x-2">
                                        <button @click="isOpenModal = true" class="rounded-md bg-primary px-3 h-8 text-[13px] text-white shadow-sm hover:bg-black focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Upload Documents') }}</button>
                                    </div>
                                </div>
                            </div>
                            <div class="w-5/5">
                                <!-- Table Component-->
                                <DocumentTable :rows="props.rows" :filters="props.filters"/>
                                <div class="px-4 pb-4">
                                    <Pagination class="mt-3" :pagination="props.rows.meta"/>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :label="$t('AI Assistant Setup')" :isOpen=isOpenFormModal>
            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4">
                <form @submit.prevent="submitForm2()" class="grid gap-x-6 gap-y-4 sm:grid-cols-6">
                    <div class="sm:col-span-6 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ $t('Key policy') }}: {{ keyPolicyLabel }}.
                        <span v-if="hasGlobalKey">{{ $t('Global API key is available from admin settings.') }}</span>
                        <span v-else>{{ $t('Global API key is not configured yet.') }}</span>
                    </div>
                    <FormSelect
                        v-if="showKeySourceSelector"
                        v-model="form2.key_source"
                        :error="form2.errors.key_source"
                        :name="$t('Key source')"
                        :type="'text'"
                        :options="keySourceOptions"
                        :class="'sm:col-span-6'"
                    />
                    <FormInput v-if="canSetOrgKey" v-model="form2.api_key" :error="form2.errors.api_key" :name="$t('OpenAI API Key')" :type="'password'" :class="'sm:col-span-6'"/>
                    <div v-else class="sm:col-span-6 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        {{ $t('Organization API key is blocked by admin policy or your current plan.') }}
                    </div>
                    <FormSelect v-model="form2.model" :error="form2.errors.model" :name="$t('Model')" :type="'text'"  :options="models" :class="'sm:col-span-6'"/>
                    <FormSelect
                        v-model="form2.embedding_model"
                        :error="form2.errors.embedding_model"
                        :name="$t('Embedding model')"
                        :type="'text'"
                        :options="embeddingModels"
                        :class="'sm:col-span-6'"
                    />
                    <div class="sm:col-span-6 border rounded-md p-2">
                        <div class="flex sm:col-span-6 grid grid-cols-6">
                            <div :class="'sm:col-span-3'">
                                <label class="block text-sm leading-6 text-gray-900 mb-1">{{ $t('Activate audio responses') }}</label>
                                <FormToggleSwitch v-model="form2.allow_audio_response" :error="form2.errors.allow_audio_response" :class="'sm:col-span-6'" :disabled="!isAudioResponseModelSelected"/>
                            </div>
                            <FormSelect v-model="form2.voice" :error="form2.errors.voice" :name="$t('Audio voice')" :type="'text'"  :options="voices" :class="'sm:col-span-3'"/>
                        </div>
                        <div class="sm:col-span-6 bg-[#ffe5b4] rounded-md px-3 py-1 mt-2">
                            <span class="block text-xs leading-6 text-gray-900">
                                {{ $t('Audio responses require an audio-capable model.') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 flex">
                        <button v-if="!aiConfigured" type="button" @click.self="isOpenFormModal = false; form2.active = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 mr-4">{{ $t('Cancel') }}</button>
                        <button v-else type="button" @click.self="isOpenFormModal = false" class="inline-flex justify-center rounded-md border border-transparent bg-slate-50 px-4 py-2 text-sm text-slate-500 hover:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 mr-4">{{ $t('Cancel') }}</button>
                        <button :class="['inline-flex justify-center rounded-md border border-transparent bg-primary px-4 py-2 text-sm text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2', { 'opacity-50': form.processing }]" :disabled="form2.processing">
                            <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </SettingLayout>

    <DocumentUploadModal :type="'contact'" v-model:modelValue="isOpenModal"/>
</template>
<script setup>
    import SettingLayout from "./../../../../resources/js/Pages/User/Automation/Layout.vue";
    import { computed, ref, watch } from 'vue';
    import { router, useForm } from "@inertiajs/vue3";
    import { trans } from 'laravel-vue-i18n';
    import DocumentTable from '@/Components/Tables/DocumentTable.vue';
    import DocumentUploadModal from '@/Components/DocumentUploadModal.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import FormTextArea from '@/Components/FormTextArea.vue';
    import FormToggleSwitch from '@/Components/FormToggleSwitch.vue';
    import Modal from '@/Components/Modal.vue';
    import Pagination from '@/Components/Pagination.vue';

    const props = defineProps(['rows', 'filters', 'settings', 'aimodule', 'fbmodule', 'models', 'embeddingModels', 'audioResponseModels', 'voices', 'aiUsageSummary']);
    const config = ref(props.settings.metadata);
    const settings = ref(config.value ? JSON.parse(config.value) : null);
    const aiConfigured = computed(() => Boolean(settings.value?.ai?.has_api_key || settings.value?.ai?.api_key));
    const aiUsageSummary = computed(() => props.aiUsageSummary ?? null);
    const aiUsageMetrics = computed(() => (aiUsageSummary.value?.metrics ?? []).filter((metric) => ['ai_text', 'ai_audio', 'ai_system_key'].includes(metric.key)));
    const aiUsageNotices = computed(() => aiUsageSummary.value?.notices ?? []);
    const keyPolicy = computed(() => settings.value?.ai?.key_policy ?? 'hybrid');
    const hasGlobalKey = computed(() => Boolean(settings.value?.ai?.has_global_key));
    const canSetOrgKey = computed(() => Boolean(settings.value?.ai?.can_set_org_key));
    const keyPolicyLabel = computed(() => {
        if (keyPolicy.value === 'global_only') return trans('Global only');
        if (keyPolicy.value === 'organization_only') return trans('Organization only');
        return trans('Hybrid');
    });
    const effectiveKeySourceLabel = computed(() => {
        const source = settings.value?.ai?.effective_key_source ?? settings.value?.ai?.key_source ?? 'auto';
        if (source === 'organization') return trans('Organization key');
        if (source === 'global') return trans('Global key');
        return trans('Auto');
    });
    const showKeySourceSelector = computed(() => keyPolicy.value === 'hybrid');
    const keySourceOptions = computed(() => {
        const options = [
            { value: 'auto', label: trans('Auto') },
            { value: 'global', label: trans('Global key') },
        ];

        if (canSetOrgKey.value) {
            options.push({ value: 'organization', label: trans('Organization key') });
        }

        return options;
    });
    const audioResponseModelSet = computed(() => {
        const models = Array.isArray(props.audioResponseModels) ? props.audioResponseModels : [];
        return new Set(models);
    });
    const isAudioResponseModelSelected = computed(() => audioResponseModelSet.value.has(form2.model));
    const isOpenModal = ref(false);
    const isOpenFormModal = ref(false);
    const setupForm = ref(false);

    const form = useForm({
        active: settings.value?.ai?.active ?? false,
    });

    const form2 = useForm({
        active: settings.value?.ai?.active ?? false,
        api_key: null,
        key_source: settings.value?.ai?.key_source ?? 'auto',
        model: settings.value?.ai?.model ?? 'gpt-4o-mini',
        embedding_model: settings.value?.ai?.embedding_model ?? 'text-embedding-3-small',
        voice: settings.value?.ai?.voice ?? null,
        allow_audio_response: settings.value?.ai?.allow_audio_response ?? false,
        max_tokens: settings.value?.ai?.max_tokens ?? 512,
        temperature: settings.value?.ai?.temperature ?? 0.7
    });

    const form3 = useForm({
        enable_automatic_responses: settings.value?.ai?.enable_automatic_responses ?? false,
        start_keywords: settings.value?.ai?.start_keywords ?? null,
        stop_keywords: settings.value?.ai?.stop_keywords ?? null,
    });

    const toggleSetupForm = () => {
        setupForm.value = !setupForm.value;
    }

    const handleActivationToggle = (value) => {
        form.active = value;
        submitForm();
    };

    const handleSetupToggle = (value) => {
        form2.active = value;

        if (value) {
            isOpenFormModal.value = true;
        }
    };

    const submitForm = async () => {
        form.post('/automation/ai/activate', {
            preserveScroll: true,
        })
    };

    const submitForm2 = () => {
        if (!canSetOrgKey.value) {
            form2.api_key = null;
        }

        if (keyPolicy.value === 'global_only') {
            form2.key_source = 'global';
        } else if (keyPolicy.value === 'organization_only') {
            form2.key_source = 'organization';
        }

        form2.post('/automation/ai/setup', {
            preserveScroll: true,
            onSuccess: () => {
                router.visit('/automation/ai', {
                    preserveState: false,
                });
            }
        })
    }

    const submitForm3 = () => {
        form3.post('/automation/ai/assistant-setup', {
            preserveScroll: true,
            onSuccess: () => {
                router.visit('/automation/ai', {
                    preserveState: false,
                });
            }
        })
    }

    watch(() => form2.model, (newValue) => {
        if (!audioResponseModelSet.value.has(newValue)) {
            form2.allow_audio_response = false;
        }
    });

    watch([canSetOrgKey, hasGlobalKey, keyPolicy], ([allowed, globalKeyAvailable, policy]) => {
        if (allowed || form2.key_source !== 'organization') {
            return;
        }

        if (policy === 'global_only' || globalKeyAvailable) {
            form2.key_source = 'global';
            return;
        }

        form2.key_source = 'auto';
    }, { immediate: true });

    const formatUsageLimit = (limit) => limit < 0 ? trans('Unlimited') : limit;
    const metricCardClasses = (metric) => {
        if (metric.status === 'exceeded') return 'border-red-200 bg-red-50/70';
        if (metric.status === 'warning') return 'border-amber-200 bg-amber-50/70';
        return 'border-slate-200 bg-slate-50';
    };
    const metricProgressClasses = (metric) => {
        if (metric.status === 'exceeded') return 'bg-red-500';
        if (metric.status === 'warning') return 'bg-amber-500';
        return 'bg-primary';
    };
    const noticeClasses = (notice) => {
        if (notice.type === 'danger') return 'border-red-200 bg-red-50 text-red-950';
        if (notice.type === 'warning') return 'border-amber-200 bg-amber-50 text-amber-950';
        return 'border-sky-200 bg-sky-50 text-sky-950';
    };
    const noticeIconClasses = (notice) => {
        if (notice.type === 'danger') return 'bg-red-100 text-red-700';
        if (notice.type === 'warning') return 'bg-amber-100 text-amber-700';
        return 'bg-sky-100 text-sky-700';
    };
    const noticeIconSvg = (notice) => {
        if (notice.type === 'danger') {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2L1 21h22zm0 4.84L19.53 19H4.47zM11 10h2v5h-2zm0 6h2v2h-2z"/></svg>';
        }

        if (notice.type === 'warning') {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M1 21h22L12 2zm12-3h-2v-2h2zm0-4h-2v-4h2z"/></svg>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M11 9h2V7h-2zm0 8h2v-6h-2zm1-15C6.48 2 2 6.48 2 12s4.48 10 10 10s10-4.48 10-10S17.52 2 12 2"/></svg>';
    };
    const metricIcon = (key) => ({
        ai_text: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2m-1 15H9v-2h2zm4-4H9V7h6z"/></svg>',
        ai_audio: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M14 3.23v17.54a1 1 0 0 1-1.64.77L7 17H3a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1h4l5.36-4.54A1 1 0 0 1 14 3.23m3.54 4.05l1.42-1.42A8 8 0 0 1 21 12a8 8 0 0 1-2.04 5.14l-1.42-1.42A6 6 0 0 0 19 12a6 6 0 0 0-1.46-4.72"/></svg>',
        ai_system_key: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M7 14a3 3 0 1 1 2.83-4H20v2h-2v2h-2v-2h-2v-2H9.83A3 3 0 0 1 7 14m0-2a1 1 0 1 0 0-2a1 1 0 0 0 0 2m10 10a3 3 0 0 1-2.83-2H4v-2h10.17A3 3 0 1 1 17 22m0-2a1 1 0 1 0 0-2a1 1 0 0 0 0 2"/></svg>',
    }[key] ?? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M11 17h2v-6h-2zm0-8h2V7h-2zm1 13C6.48 22 2 17.52 2 12S6.48 2 12 2s10 4.48 10 10s-4.48 10-10 10"/></svg>');
</script>
