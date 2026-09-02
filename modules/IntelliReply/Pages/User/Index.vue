<template>
    <SettingLayout :aimodule="aimodule" :fbmodule="fbmodule">
        <div class="ai-page mx-auto w-full max-w-4xl pb-16">
            <div class="mb-5">
                <h1 class="text-xl font-semibold text-[var(--ui-text)]">{{ $t('AI Reply Assistant') }}</h1>
                <p class="mt-1 text-sm text-[var(--ui-muted)]">{{ $t('Configure how AI responds to your customers on WhatsApp.') }}</p>
            </div>

            <!-- Card 1: Enable AI Assistant -->
            <section class="ai-card">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="ai-card-title">{{ $t('Enable AI Assistant') }}</h2>
                        <p class="ai-card-desc">{{ $t('Activate AI-generated responses in your conversations') }}</p>
                        <p class="mt-2 text-xs text-[var(--ui-muted)]">
                            {{ $t('Key policy') }}: {{ keyPolicyLabel }} &middot; {{ $t('Current key source') }}: {{ effectiveKeySourceLabel }}
                        </p>
                    </div>
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
                </div>
            </section>

            <!-- Card 2: Model & Connection Setup (previously a modal) -->
            <section v-if="showSetupCard" class="ai-card">
                <div class="mb-4">
                    <h2 class="ai-card-title">{{ $t('Model & Connection Setup') }}</h2>
                    <p class="ai-card-desc">{{ $t('Configure the AI provider, model, and connection used to generate replies.') }}</p>
                </div>

                <form @submit.prevent="submitForm2()" class="grid grid-cols-1 gap-4 sm:grid-cols-6">
                    <div class="ai-note sm:col-span-6">
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
                    <div v-else class="ai-note sm:col-span-6">
                        {{ $t('Organization API key is blocked by admin policy or your current plan.') }}
                    </div>

                    <FormSelect v-model="form2.model" :error="form2.errors.model" :name="$t('Model')" :type="'text'" :options="models" :class="'sm:col-span-3'"/>
                    <FormSelect
                        v-model="form2.embedding_model"
                        :error="form2.errors.embedding_model"
                        :name="$t('Embedding model')"
                        :type="'text'"
                        :options="embeddingModels"
                        :class="'sm:col-span-3'"
                    />

                    <div class="sm:col-span-3">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="ui-form-label">{{ $t('Response creativity (temperature)') }}</label>
                            <span class="ai-slider-value">{{ Number(form2.temperature ?? 0).toFixed(1) }}</span>
                        </div>
                        <input type="range" min="0" max="2" step="0.1" v-model.number="form2.temperature" class="ai-range"/>
                        <div class="mt-1 flex justify-between text-[11px] text-[var(--ui-muted)]">
                            <span>{{ $t('Focused') }}</span>
                            <span>{{ $t('Creative') }}</span>
                        </div>
                    </div>

                    <div class="sm:col-span-3">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="ui-form-label">{{ $t('Maximum reply length') }}</label>
                            <span class="ai-slider-value">{{ form2.max_tokens }} {{ $t('tokens') }}</span>
                        </div>
                        <input type="range" min="64" max="8000" step="8" v-model.number="form2.max_tokens" class="ai-range"/>
                    </div>

                    <div class="ai-subcard sm:col-span-6">
                        <div class="flex flex-wrap items-start gap-4">
                            <div class="min-w-[180px] flex-1">
                                <label class="ui-form-label mb-1 block">{{ $t('Activate audio responses') }}</label>
                                <FormToggleSwitch v-model="form2.allow_audio_response" :error="form2.errors.allow_audio_response" :disabled="!isAudioResponseModelSelected"/>
                            </div>
                            <FormSelect v-model="form2.voice" :error="form2.errors.voice" :name="$t('Audio voice')" :type="'text'" :options="voices" :class="'min-w-[180px] flex-1'"/>
                        </div>
                        <div class="ai-warning-note mt-3">
                            {{ $t('Audio responses require an audio-capable model.') }}
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3 sm:col-span-6">
                        <span v-if="form2.isDirty" class="ai-unsaved-pill">{{ $t('Unsaved changes') }}</span>
                        <button v-if="!aiConfigured" type="button" @click="cancelSetup" class="ai-btn-ghost">{{ $t('Cancel') }}</button>
                        <button type="submit" class="ai-btn-primary" :disabled="form2.processing">
                            <svg v-if="form2.processing" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Card 3: Automatic reply behavior -->
            <section v-if="aiConfigured" class="ai-card">
                <div class="flex items-center justify-between gap-3">
                    <button type="button" @click="toggleSetupForm()" class="min-w-0 flex-1 text-start">
                        <h2 class="ai-card-title">{{ $t('Automatic Replies') }}</h2>
                        <p class="ai-card-desc">{{ $t('Setup keywords for AI assistance') }}</p>
                    </button>
                    <button type="button" @click="toggleSetupForm()" class="ai-icon-btn shrink-0" :aria-label="setupForm ? $t('Collapse') : $t('Expand')">
                        <svg v-if="setupForm" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M16.53 14.03a.75.75 0 0 1-1.06 0L12 10.56l-3.47 3.47a.75.75 0 0 1-1.06-1.06l4-4a.75.75 0 0 1 1.06 0l4 4a.75.75 0 0 1 0 1.06" clip-rule="evenodd"/></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M16.53 8.97a.75.75 0 0 1 0 1.06l-4 4a.75.75 0 0 1-1.06 0l-4-4a.75.75 0 1 1 1.06-1.06L12 12.44l3.47-3.47a.75.75 0 0 1 1.06 0" clip-rule="evenodd"/></svg>
                    </button>
                </div>

                <form v-if="setupForm" @submit.prevent="submitForm3()" class="mt-4 grid grid-cols-1 gap-4">
                    <div class="ai-subcard flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <span class="text-sm font-medium text-[var(--ui-text)]">{{ $t('Enable automatic AI assistance for new conversations') }}</span>
                            <p class="mt-1 text-xs leading-5 text-[var(--ui-muted)]">
                                {{ $t('Turn on this option to let users automatically get help from the AI whenever they start a new conversation or ticket. If enabled, this will override the keywords set for initiating AI chat.') }}
                            </p>
                        </div>
                        <FormToggleSwitch
                            :modelValue="form3.enable_automatic_responses"
                            @update:modelValue="value => form3.enable_automatic_responses = value"
                        />
                    </div>

                    <div>
                        <FormInput v-model="form3.start_keywords" :error="form3.errors.start_keywords" :label="$t('Keyword(s) to start the AI agent')" :name="$t('Keyword(s) to start the AI agent')" :type="'text'"/>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-[var(--ui-muted)]">
                            <span>{{ $t('Set word or phrase') }}</span>
                            <span>{{ (form3.start_keywords || '').length }}/1000</span>
                        </div>
                    </div>

                    <div>
                        <FormTextArea v-model="form3.stop_keywords" :error="form3.errors.stop_keywords" :name="$t('Keyword(s) to stop the AI agent')" :textAreaRows="3"/>
                        <div class="mt-1 flex items-center justify-between text-[11px] text-[var(--ui-muted)]">
                            <span>{{ $t('Set word or phrase') }}</span>
                            <span>{{ (form3.stop_keywords || '').length }}/1000</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <span v-if="form3.isDirty" class="ai-unsaved-pill">{{ $t('Unsaved changes') }}</span>
                        <button type="submit" class="ai-btn-primary" :disabled="form3.processing">
                            <svg v-if="form3.processing" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".5"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"><animateTransform attributeName="transform" dur="1s" from="0 12 12" repeatCount="indefinite" to="360 12 12" type="rotate"/></path></svg>
                            <span v-else>{{ $t('Save') }}</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Card 4: AI usage overview -->
            <section v-if="aiUsageNotices.length || aiUsageMetrics.length" class="ai-card">
                <div class="mb-4">
                    <h2 class="ai-card-title">{{ $t('AI usage overview') }}</h2>
                    <p class="ai-card-desc">{{ $t('This snapshot tracks only AI requests billed to the global AI key.') }}</p>
                </div>

                <div v-if="aiUsageNotices.length" class="mb-4 space-y-3">
                    <div v-for="notice in aiUsageNotices" :key="notice.key" class="flex items-start gap-3 rounded-2xl border px-4 py-3" :class="noticeClasses(notice)">
                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full" :class="noticeIconClasses(notice)" v-html="noticeIconSvg(notice)"></span>
                        <div>
                            <p class="text-sm font-semibold">{{ notice.title }}</p>
                            <p class="mt-1 text-xs leading-6">{{ notice.message }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="aiUsageMetrics.length" class="grid gap-3 md:grid-cols-3">
                    <article v-for="metric in aiUsageMetrics" :key="metric.key" class="rounded-2xl border p-4" :class="metricCardClasses(metric)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-[var(--ui-text)]">{{ metric.label }}</p>
                                <p class="mt-1 text-xs leading-5 text-[var(--ui-muted)]">{{ metric.helper }}</p>
                            </div>
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[var(--ui-surface)] text-[var(--ui-secondary)] shadow-[var(--ui-shadow-1)]" v-html="metricIcon(metric.key)"></span>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-xs text-[var(--ui-muted)]">
                            <span>{{ $t('Used') }}: {{ metric.used }}</span>
                            <span>{{ $t('Limit') }}: {{ formatUsageLimit(metric.limit) }}</span>
                        </div>
                        <div v-if="metric.limit >= 0" class="mt-3">
                            <div class="mb-2 flex items-center justify-between text-xs text-[var(--ui-muted)]">
                                <span>{{ $t('Usage') }}</span>
                                <span>{{ metric.percentage ?? 0 }}%</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-[var(--ui-border)]">
                                <div class="h-full rounded-full transition-all duration-500" :class="metricProgressClasses(metric)" :style="{ width: `${metric.percentage ?? 0}%` }"></div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Card 5: Knowledge Base -->
            <section v-if="aiConfigured" class="ai-card">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="ai-card-title">{{ $t('Knowledge Base') }}</h2>
                        <p class="ai-card-desc">{{ $t('Enhance your AI assistant by uploading information to improve client interactions.') }}</p>
                    </div>
                    <button type="button" @click="isOpenModal = true" class="ai-btn-primary shrink-0">{{ $t('Upload Documents') }}</button>
                </div>
                <DocumentTable :rows="props.rows" :filters="props.filters"/>
                <Pagination class="mt-3" :pagination="props.rows.meta"/>
            </section>
        </div>
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
    const setupForm = ref(false);
    const showSetupCard = computed(() => aiConfigured.value || form2.active);

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
    };

    const cancelSetup = () => {
        if (!aiConfigured.value) {
            form2.active = false;
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
        if (metric.status === 'exceeded') return 'border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_8%,var(--ui-surface))]';
        if (metric.status === 'warning') return 'border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_8%,var(--ui-surface))]';
        return 'border-[var(--ui-border)] bg-[var(--ui-surface-soft)]';
    };
    const metricProgressClasses = (metric) => {
        if (metric.status === 'exceeded') return 'bg-[var(--ui-danger)]';
        if (metric.status === 'warning') return 'bg-[var(--ui-warning)]';
        return 'bg-[var(--ui-secondary)]';
    };
    const noticeClasses = (notice) => {
        if (notice.type === 'danger') return 'border-[color-mix(in_srgb,var(--ui-danger)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-danger)_10%,var(--ui-surface))] text-[var(--ui-danger)]';
        if (notice.type === 'warning') return 'border-[color-mix(in_srgb,var(--ui-warning)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-warning)_10%,var(--ui-surface))] text-[var(--ui-warning)]';
        return 'border-[color-mix(in_srgb,var(--ui-secondary)_35%,var(--ui-border))] bg-[color-mix(in_srgb,var(--ui-secondary)_10%,var(--ui-surface))] text-[var(--ui-secondary)]';
    };
    const noticeIconClasses = (notice) => {
        if (notice.type === 'danger') return 'bg-[color-mix(in_srgb,var(--ui-danger)_16%,var(--ui-surface))] text-[var(--ui-danger)]';
        if (notice.type === 'warning') return 'bg-[color-mix(in_srgb,var(--ui-warning)_16%,var(--ui-surface))] text-[var(--ui-warning)]';
        return 'bg-[color-mix(in_srgb,var(--ui-secondary)_16%,var(--ui-surface))] text-[var(--ui-secondary)]';
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

<style scoped>
.ai-card {
    border-radius: 22px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1rem;
}

.ai-card-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--ui-text);
}

.ai-card-desc {
    margin-top: 0.25rem;
    font-size: 0.85rem;
    line-height: 1.5;
    color: var(--ui-muted);
}

.ai-note {
    border-radius: 0.85rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.65rem 0.9rem;
    font-size: 0.78rem;
    color: var(--ui-muted);
}

.ai-warning-note {
    border-radius: 0.75rem;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    line-height: 1.4;
    color: var(--ui-warning);
}

.ai-subcard {
    border-radius: 1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 1rem;
}

.ai-unsaved-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--ui-warning) 35%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-warning) 12%, var(--ui-surface));
    padding: 0.25rem 0.65rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--ui-warning);
}

.ai-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 2.25rem;
    width: 2.25rem;
    border-radius: 999px;
    color: var(--ui-muted);
    transition: background-color 160ms ease, color 160ms ease;
}

.ai-icon-btn:hover {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
}

.ai-btn-primary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    border-radius: 0.75rem;
    background: var(--ui-primary);
    color: #fff;
    padding: 0.5rem 1.1rem;
    font-size: 0.83rem;
    font-weight: 600;
    transition: filter 160ms ease, opacity 160ms ease;
}

.ai-btn-primary:hover:not(:disabled) {
    filter: brightness(1.05);
}

.ai-btn-primary:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.ai-btn-ghost {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-text);
    padding: 0.5rem 1.1rem;
    font-size: 0.83rem;
    font-weight: 600;
    transition: background-color 160ms ease, border-color 160ms ease;
}

.ai-btn-ghost:hover {
    background: var(--ui-surface-soft);
    border-color: var(--ui-border-strong);
}

.ai-slider-value {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--ui-secondary);
}

.ai-range {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 999px;
    background: var(--ui-border);
    outline: none;
}

.ai-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--ui-secondary);
    border: 3px solid var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    cursor: pointer;
}

.ai-range::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--ui-secondary);
    border: 3px solid var(--ui-surface);
    box-shadow: var(--ui-shadow-1);
    cursor: pointer;
}

.ai-range::-moz-range-track {
    height: 6px;
    border-radius: 999px;
    background: var(--ui-border);
}
</style>
