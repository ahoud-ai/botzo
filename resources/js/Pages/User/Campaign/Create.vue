<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame ui-text-main min-h-full">
            <UiPageHeader :title="$t('Create campaign')" :subtitle="$t('Prepare your message, choose recipients, and pick a send time in 3 simple steps.')">
                <template #actions>
                    <Link href="/campaigns" class="wizard-btn wizard-btn--ghost">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><path fill="currentColor" fill-rule="evenodd" d="M5.841 5.28a.75.75 0 0 0-1.06-1.06L1.53 7.47L1 8l.53.53l3.25 3.25a.75.75 0 0 0 1.061-1.06l-1.97-1.97H24.25a.75.75 0 0 0 0-1.5H3.871l1.97-1.97Z" clip-rule="evenodd"/></svg>
                        {{ $t('Back') }}
                    </Link>
                </template>
            </UiPageHeader>

            <!-- WhatsApp not connected guard -->
            <div v-if="!waSettings?.whatsapp" class="wizard-connect-card">
                <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 48 48"><path fill="currentColor" d="M43.634 4.366a1.25 1.25 0 0 1 0 1.768l-4.913 4.913a9.253 9.253 0 0 1-.744 12.244l-3.343 3.343a1.25 1.25 0 0 1-1.768 0l-11.5-11.5a1.25 1.25 0 0 1 0-1.768l3.343-3.343a9.25 9.25 0 0 1 12.244-.743l4.913-4.914a1.25 1.25 0 0 1 1.768 0m-7.611 7.425a6.75 6.75 0 0 0-9.546 0l-2.46 2.459l9.733 9.732l2.46-2.459a6.75 6.75 0 0 0 0-9.546zM9.28 36.953l-4.914 4.913a1.25 1.25 0 0 0 1.768 1.768l4.913-4.913a9.253 9.253 0 0 0 12.244-.744l3.343-3.343a1.25 1.25 0 0 0 0-1.768L25.268 31.5l3.366-3.366a1.25 1.25 0 0 0-1.768-1.768L23.5 29.732L18.268 24.5l3.366-3.366a1.25 1.25 0 0 0-1.768-1.768L16.5 22.732l-1.366-1.366a1.25 1.25 0 0 0-1.768 0l-3.343 3.343a9.25 9.25 0 0 0-.743 12.244m2.51-10.476l2.46-2.46l9.732 9.733l-2.459 2.46a6.75 6.75 0 0 1-9.546 0l-.186-.187a6.75 6.75 0 0 1 0-9.546"/></svg>
                <h3>{{ $t('Connect your whatsapp account') }}</h3>
                <p>{{ $t('You need to connect your WhatsApp account first before you can send out campaigns.') }}</p>
                <Link href="/settings/whatsapp" class="wizard-btn wizard-btn--solid">{{ $t('Connect Whatsapp account') }}</Link>
            </div>

            <template v-else>
                <!-- Stepper -->
                <div class="wizard-stepper">
                    <template v-for="(step, idx) in steps" :key="step.num">
                        <button
                            type="button"
                            class="wizard-step"
                            :class="{ 'is-active': currentStep === step.num, 'is-done': currentStep > step.num }"
                            @click="tryGoToStep(step.num)"
                        >
                            <span class="wizard-step-circle">
                                <svg v-if="currentStep > step.num" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                                <template v-else>{{ step.num }}</template>
                            </span>
                            <span class="wizard-step-text">
                                <span class="wizard-step-label">{{ step.label }}</span>
                                <span class="wizard-step-hint">{{ step.hint }}</span>
                            </span>
                        </button>
                        <div v-if="idx < steps.length - 1" class="wizard-step-line" :class="{ 'is-done': currentStep > step.num }"></div>
                    </template>
                </div>

                <div class="wizard-layout">
                    <div class="wizard-main">
                        <!-- Step 1: Template & content -->
                        <div v-show="currentStep === 1" class="wizard-card">
                            <h2 class="wizard-card-title">{{ $t('Choose a template') }}</h2>
                            <p class="wizard-card-desc">{{ $t('The message that will be sent to all recipients.') }}</p>

                            <div v-if="templates.length" class="wizard-picker-grid">
                                <button
                                    type="button"
                                    v-for="tpl in templates"
                                    :key="tpl.uuid"
                                    class="wizard-picker-card"
                                    :class="{ 'is-selected': form.template === tpl.uuid }"
                                    @click="selectTemplate(tpl)"
                                >
                                    <span class="wizard-picker-check">
                                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
                                    </span>
                                    <span class="wizard-picker-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10Z"/></svg>
                                    </span>
                                    <span class="wizard-picker-name">{{ tpl.name }}</span>
                                    <span class="wizard-picker-cat">{{ categoryLabel(tpl.category) }}</span>
                                </button>
                            </div>
                            <div v-else class="wizard-empty-note">
                                {{ $t('You don\'t have any templates') }}
                                <Link href="/templates/create" class="wizard-inline-link">{{ $t('Create template') }}</Link>
                            </div>
                            <div v-if="form.errors.template" class="ui-form-error mt-2">{{ form.errors.template }}</div>

                            <div v-if="form.header.parameters.length > 0" class="wizard-var-block">
                                <h3 class="wizard-var-title">{{ $t('Header variables') }}</h3>
                                <div v-for="(item, index) in form.header.parameters" :key="index" class="wizard-var-row">
                                    <template v-if="form.header.parameters[index].type === 'text'">
                                        <FormSelect v-model="form.header.parameters[index].selection" :name="$t('Content type')" :options="variableOptions" :class="'w-full sm:w-auto'"/>
                                        <FormInput v-if="form.header.parameters[index].selection === 'static'" :name="$t('Value')" :required="true" :error="form.errors['header.parameters.0.value']" v-model="form.header.parameters[index].value" :type="'text'" :class="'w-full'"/>
                                        <FormSelect v-if="form.header.parameters[index].selection === 'dynamic'" :name="$t('Value')" :required="true" :error="form.errors['header.parameters.0.value']" v-model="form.header.parameters[index].value" :options="dynamicOptions" :class="'w-full'"/>
                                    </template>
                                    <div v-if="['IMAGE', 'DOCUMENT', 'VIDEO'].includes(form.header.parameters[index].type)" class="w-full">
                                        <div class="flex items-center gap-x-4">
                                            <label class="wizard-upload-btn" :class="form.errors['header.parameters.0.value'] ? 'wizard-upload-btn--error' : ''" for="file-upload">{{ $t('Upload') }}</label>
                                            <input type="file" class="sr-only" :accept="getFileAcceptAttribute(form.header.parameters[index].type)" ref="fileInput" id="file-upload" @change="handleFileUpload"/>
                                            <div v-if="form.header.parameters[index].value" class="w-[16em] truncate text-sm">{{ form.header.parameters[index].selection === 'default' ? form.header.parameters[index].value : form.header.parameters[index].value.name }}</div>
                                            <span v-else class="text-sm text-[var(--ui-muted)]">{{ $t('No file chosen') }}</span>
                                        </div>
                                        <div v-if="form.errors['header.parameters.0.value']" class="ui-form-error mt-2">{{ form.errors['header.parameters.0.value'] }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.body.parameters.length > 0" class="wizard-var-block">
                                <h3 class="wizard-var-title">{{ $t('Body variables') }}</h3>
                                <div v-for="(item, index) in form.body.parameters" :key="index" class="wizard-var-row">
                                    <span class="wizard-var-index">{{ variablePlaceholder(index) }}</span>
                                    <FormSelect v-model="form.body.parameters[index].selection" :options="variableOptions" :class="'w-full sm:w-auto'"/>
                                    <FormInput v-if="form.body.parameters[index].selection === 'static'" v-model="form.body.parameters[index].value" :required="true" :error="form.errors['body.parameters.0.value']" :type="'text'" :class="'w-full'"/>
                                    <FormSelect v-if="form.body.parameters[index].selection === 'dynamic'" v-model="form.body.parameters[index].value" :required="true" :error="form.errors['body.parameters.0.value']" :options="dynamicOptions" :class="'w-full'"/>
                                </div>
                            </div>

                            <div v-if="form.buttons.length > 0" class="wizard-var-block">
                                <h3 class="wizard-var-title">{{ $t('Button variables') }}</h3>
                                <div v-for="(item, index) in form.buttons" :key="index">
                                    <div v-if="item.parameters.length > 0" class="wizard-var-button">
                                        <div class="wizard-var-button-label">{{ $t('Label') }}: {{ item.text }}</div>
                                        <div v-for="(value, key) in item.parameters" :key="key" class="wizard-var-row">
                                            <FormSelect v-model="value.type" :name="$t('Button type')" :options="variableOptions" :class="'w-full sm:w-auto'"/>
                                            <FormInput v-if="value.type === 'static'" v-model="value.value" :name="$t('Value')" :required="true" :error="form.errors['buttons.'+ index +'.parameters.0.value']" :type="'text'" :class="'w-full'"/>
                                            <FormSelect v-if="value.type === 'dynamic'" v-model="value.value" :name="$t('Value')" :required="true" :error="form.errors['buttons.'+ index +'.parameters.0.value']" :options="dynamicOptions" :class="'w-full'"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-actions">
                                <span></span>
                                <button type="button" class="wizard-btn wizard-btn--solid" :disabled="!canProceedFromStep(1)" @click="nextStep">{{ $t('Next') }}</button>
                            </div>
                        </div>

                        <!-- Step 2: Recipients -->
                        <div v-show="currentStep === 2" class="wizard-card">
                            <h2 class="wizard-card-title">{{ $t('Recipients') }}</h2>
                            <p class="wizard-card-desc">{{ $t('Name your campaign and choose recipients.') }}</p>

                            <FormInput v-model="form.name" :name="$t('Campaign name')" :type="'text'" :error="form.errors.name" :required="true" :class="'mb-5'"/>

                            <label class="ui-form-label mb-2 block">{{ $t('Send to') }}</label>
                            <div v-if="recipientOptions.length > 1" class="wizard-recipient-grid">
                                <button
                                    type="button"
                                    v-for="option in recipientOptions"
                                    :key="option.value"
                                    class="wizard-recipient-card"
                                    :class="{ 'is-selected': form.contacts === option.value }"
                                    @click="form.contacts = option.value"
                                >
                                    <span class="wizard-recipient-name">{{ option.label }}</span>
                                    <span class="wizard-recipient-count">{{ option.count }}</span>
                                </button>
                            </div>
                            <div v-else class="wizard-empty-note">
                                {{ $t('No contacts available') }}
                                <Link href="/contacts" class="wizard-inline-link">{{ $t('Contact') }}</Link>
                            </div>
                            <div v-if="form.errors.contacts" class="ui-form-error mt-2">{{ form.errors.contacts }}</div>

                            <div class="wizard-actions">
                                <button type="button" class="wizard-btn wizard-btn--ghost" @click="prevStep">{{ $t('Back') }}</button>
                                <button type="button" class="wizard-btn wizard-btn--solid" :disabled="!canProceedFromStep(2)" @click="nextStep">{{ $t('Next') }}</button>
                            </div>
                        </div>

                        <!-- Step 3: Schedule & review -->
                        <div v-show="currentStep === 3" class="wizard-card">
                            <h2 class="wizard-card-title">{{ $t('Schedule & review') }}</h2>
                            <p class="wizard-card-desc">{{ $t('Choose when to send and confirm the details.') }}</p>

                            <div class="wizard-segmented">
                                <button type="button" :class="{ 'is-active': form.skip_schedule }" @click="form.skip_schedule = true">{{ $t('Send now') }}</button>
                                <button type="button" :class="{ 'is-active': !form.skip_schedule }" @click="form.skip_schedule = false">{{ $t('Schedule for later') }}</button>
                            </div>

                            <FormInput v-if="!form.skip_schedule" v-model="form.time" :name="$t('Scheduled time')" :type="'datetime-local'" :error="form.errors.time" :required="true" :class="'mb-2'"/>

                            <div v-if="!limitIsUnlimited" class="wizard-usage-box" :class="{ 'is-danger': limitReached }">
                                <div class="wizard-usage-row">
                                    <span>{{ $t('Campaign limit') }}</span>
                                    <span>{{ campaignLimit.used }} / {{ campaignLimit.limit }}</span>
                                </div>
                                <div class="wizard-usage-track">
                                    <div class="wizard-usage-fill" :style="{ width: limitPercent + '%' }"></div>
                                </div>
                                <p v-if="limitReached" class="wizard-usage-warning">
                                    {{ $t('You have reached your limit of campaigns. Please upgrade your account to add more!') }}
                                    <Link href="/subscription" class="wizard-inline-link">{{ $t('Manage plans') }}</Link>
                                </p>
                            </div>

                            <dl class="wizard-review-list">
                                <div class="wizard-review-row">
                                    <dt>{{ $t('Campaign name') }}</dt>
                                    <dd>{{ form.name || '-' }}</dd>
                                </div>
                                <div class="wizard-review-row">
                                    <dt>{{ $t('Template') }}</dt>
                                    <dd>{{ selectedTemplateObj?.name || '-' }}</dd>
                                </div>
                                <div class="wizard-review-row">
                                    <dt>{{ $t('Recipients') }}</dt>
                                    <dd>{{ selectedRecipient ? selectedRecipient.label + ' (' + selectedRecipient.count + ')' : '-' }}</dd>
                                </div>
                                <div class="wizard-review-row">
                                    <dt>{{ $t('Send time') }}</dt>
                                    <dd>{{ form.skip_schedule ? $t('Immediately after confirmation') : formattedScheduleTime }}</dd>
                                </div>
                            </dl>

                            <div class="wizard-actions">
                                <button type="button" class="wizard-btn wizard-btn--ghost" @click="prevStep">{{ $t('Back') }}</button>
                                <button type="button" class="wizard-btn wizard-btn--solid" :disabled="form.processing || limitReached" @click="submitForm">
                                    <span v-if="!form.processing">{{ $t('Create campaign') }}</span>
                                    <svg v-else class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".3"/><path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="wizard-preview">
                        <div class="wizard-preview-frame">
                            <div class="wizard-preview-topbar">
                                <span class="wizard-preview-avatar">{{ orgInitial }}</span>
                                <div>
                                    <div class="wizard-preview-title">{{ orgName }}</div>
                                    <div class="wizard-preview-sub">{{ $t('Live preview') }}</div>
                                </div>
                            </div>
                            <div class="ui-workspace-main--chat wizard-preview-body">
                                <WhatsappTemplate :parameters="form" :visible="form.template ? true : false"/>
                            </div>
                        </div>
                        <p class="wizard-preview-hint">{{ $t('This preview updates instantly as you edit the template.') }}</p>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
<script setup>
    import axios from "axios";
    import AppLayout from "./../Layout/App.vue";
    import FormInput from '@/Components/FormInput.vue';
    import FormSelect from '@/Components/FormSelect.vue';
    import WhatsappTemplate from '@/Components/WhatsappTemplate.vue';
    import UiPageHeader from '@/Components/UI/UiPageHeader.vue';
    import { ref, computed } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    const { t } = useI18n();

    const props = defineProps({
        templates: { type: Array, default: () => [] },
        contactGroups: { type: Array, default: () => [] },
        contactFields: { type: Array, default: () => [] },
        settings: { type: Object, default: null },
        totalContacts: { type: Number, default: 0 },
        campaignLimit: { type: Object, default: () => ({ limit: -1, used: 0, reached: false }) },
    });

    const config = ref(props.settings?.metadata);
    const waSettings = ref(config.value ? JSON.parse(config.value) : null);
    const orgName = props.settings?.name ?? '';
    const orgInitial = (orgName || '?').trim().charAt(0).toUpperCase();

    const currentStep = ref(1);
    const steps = computed(() => ([
        { num: 1, label: t('Template & content'), hint: t('Choose a template') },
        { num: 2, label: t('Recipients'), hint: t('Name your campaign and choose recipients.') },
        { num: 3, label: t('Schedule & review'), hint: t('Choose when to send and confirm the details.') },
    ]));

    const variableOptions = ref([
        { value: 'static', label: t('static') },
        { value: 'dynamic', label: t('dynamic') }
    ]);

    const dynamicOptions = computed(() => {
        const baseOptions = [
            { value: 'first name', label: t('contact first name') },
            { value: 'last name', label: t('contact last name') },
            { value: 'name', label: t('contact full name') },
            { value: 'phone', label: t('contact phone') },
            { value: 'email', label: t('contact email') },
        ];

        if (props.contactFields && Array.isArray(props.contactFields)) {
            const customFields = props.contactFields.map(field => ({
                value: `field:${field.name}`,
                label: field.name
            }));
            return [...baseOptions, ...customFields];
        }

        return baseOptions;
    });

    const variablePlaceholder = (index) => '{' + '{' + (index + 1) + '}' + '}';

    const categoryLabels = { MARKETING: 'Marketing', UTILITY: 'Utility', AUTHENTICATION: 'Authentication' };
    const categoryLabel = (category) => {
        const key = categoryLabels[String(category ?? '').toUpperCase()];
        return key ? t(key) : (category ?? '-');
    };

    const form = useForm({
        name: null,
        template: null,
        contacts: null,
        time: null,
        skip_schedule: false,
        'header': {
            'format': null,
            'text': null,
            'parameters': []
        },
        'body': {
            'text': null,
            'parameters': []
        },
        'footer': {
            'text': null,
        },
        'buttons': [],
    });

    const extractComponent = (data, type, customProperty) => {
        const component = data.components.find((c) => c.type === type);
        return component ? component[customProperty] : null;
    };

    const loadTemplate = async (uuid) => {
        try {
            const response = await axios.get('/templates/' + uuid);
            if (response) {
                const metadata = JSON.parse(response.data.metadata);
                form.header.format = extractComponent(metadata, 'HEADER', 'format');
                form.header.text = extractComponent(metadata, 'HEADER', 'text');
                const headerExamples = extractComponent(metadata, 'HEADER', 'example');

                if (headerExamples) {
                    if (form.header.format === 'TEXT') {
                        form.header.parameters = headerExamples.header_text.map(item => ({
                            type: 'text',
                            selection: 'static',
                            value: item,
                        }));
                    } else if (['IMAGE', 'DOCUMENT', 'VIDEO'].includes(form.header.format)) {
                        form.header.parameters = headerExamples.header_handle.map(item => ({
                            type: form.header.format,
                            selection: 'default',
                            value: null,
                            url: item,
                        }));
                    }
                } else {
                    form.header.parameters = [];
                }

                form.body.text = extractComponent(metadata, 'BODY', 'text');
                const bodyExamples = extractComponent(metadata, 'BODY', 'example');
                if (bodyExamples) {
                    form.body.parameters = bodyExamples.body_text[0].map(item => ({
                        type: 'text',
                        selection: 'static',
                        value: item,
                    }));
                } else {
                    form.body.parameters = [];
                }

                form.footer.text = extractComponent(metadata, 'FOOTER', 'text');

                const buttons = extractComponent(metadata, 'BUTTONS', 'buttons');
                if (buttons) {
                    form.buttons = buttons.map(item => ({
                        type: item.type,
                        text: item.text,
                        value: item[item.type.toLowerCase()] ?? null,
                        parameters: (item.type === 'QUICK_REPLY')
                            ? [{ type: 'static', value: null }]
                            : (item.example ? item.example.map(param => ({ type: 'static', value: param })) : []),
                    }));
                } else {
                    form.buttons = [];
                }
            }
        } catch {
            // Template metadata is optional while composing campaign content.
        }
    };

    const selectTemplate = (tpl) => {
        form.template = tpl.uuid;
        loadTemplate(tpl.uuid);
    };

    const selectedTemplateObj = computed(() => props.templates.find(tpl => tpl.uuid === form.template) ?? null);

    const getFileSizeLimit = (fileType) => {
        switch (fileType) {
            case 'IMAGE': return 5 * 1024 * 1024;
            case 'DOCUMENT': return 100 * 1024 * 1024;
            case 'VIDEO': return 16 * 1024 * 1024;
            default: return Infinity;
        }
    };

    const getFileAcceptAttribute = (fileType) => {
        switch (fileType) {
            case 'IMAGE': return '.png, .jpg';
            case 'DOCUMENT': return '.pdf, .txt, .ppt, .doc, .xls, .docx, .pptx, .xlsx';
            case 'VIDEO': return '.mp4';
            default: return '';
        }
    };

    const handleFileUpload = (event) => {
        const fileSizeLimit = getFileSizeLimit(form.header.parameters[0].type);
        const file = event.target.files[0];

        if (file && file.size > fileSizeLimit) {
            alert(t('file size exceeds the limit. Max allowed size:') + ' ' + fileSizeLimit + 'b');
            event.target.value = null;
        } else {
            const reader = new FileReader();
            reader.onload = (e) => {
                form.header.parameters[0].url = e.target.result;
            };
            form.header.parameters[0].selection = 'upload';
            form.header.parameters[0].value = file;
            reader.readAsDataURL(file);
        }
    };

    const recipientOptions = computed(() => {
        const groups = (props.contactGroups || []).map(g => ({
            value: g.uuid,
            label: g.name,
            count: g.contacts_count ?? 0,
        }));
        return [{ value: 'all', label: t('all contacts'), count: props.totalContacts }, ...groups];
    });

    const selectedRecipient = computed(() => recipientOptions.value.find(r => r.value === form.contacts) ?? null);

    const formattedScheduleTime = computed(() => {
        if (!form.time) return '-';
        const date = new Date(form.time);
        if (Number.isNaN(date.getTime())) return form.time;
        return new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
    });

    const limitIsUnlimited = computed(() => (props.campaignLimit?.limit ?? -1) === -1);
    const limitReached = computed(() => !limitIsUnlimited.value && props.campaignLimit?.reached === true);
    const limitPercent = computed(() => {
        if (limitIsUnlimited.value) return 0;
        const limit = props.campaignLimit?.limit ?? 0;
        const used = props.campaignLimit?.used ?? 0;
        if (!limit) return 100;
        return Math.max(0, Math.min(100, Math.round((used / limit) * 100)));
    });

    const canProceedFromStep = (step) => {
        if (step === 1) return !!form.template;
        if (step === 2) return !!form.name && !!form.contacts;
        return true;
    };

    const nextStep = () => {
        if (!canProceedFromStep(currentStep.value)) return;
        currentStep.value = Math.min(3, currentStep.value + 1);
    };
    const prevStep = () => {
        currentStep.value = Math.max(1, currentStep.value - 1);
    };
    const tryGoToStep = (n) => {
        if (n <= currentStep.value) {
            currentStep.value = n;
            return;
        }
        for (let s = currentStep.value; s < n; s++) {
            if (!canProceedFromStep(s)) return;
        }
        currentStep.value = n;
    };

    const submitForm = () => {
        form.post('/campaigns', {
            onError: (errors) => {
                const keys = Object.keys(errors);
                if (keys.some(k => k === 'template' || k.startsWith('header') || k.startsWith('body') || k.startsWith('buttons'))) {
                    currentStep.value = 1;
                } else if (keys.some(k => k === 'name' || k === 'contacts')) {
                    currentStep.value = 2;
                } else if (keys.some(k => k === 'time')) {
                    currentStep.value = 3;
                }
            },
        });
    };
</script>

<style scoped>
.wizard-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.7rem;
    padding: 0.65rem 1.2rem;
    font-size: 0.85rem;
    font-weight: 600;
}

.wizard-btn--solid {
    background: var(--ui-secondary);
    color: #fff;
    border: none;
    transition: filter 160ms ease;
}
.wizard-btn--solid:hover:not(:disabled) { filter: brightness(1.05); }
.wizard-btn--solid:disabled { opacity: 0.55; cursor: not-allowed; }

.wizard-btn--ghost {
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    border: 1px solid var(--ui-border);
}
.wizard-btn--ghost:hover { background: var(--ui-border); }

.wizard-connect-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 0.75rem;
    padding: 3rem 1.5rem;
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    background: var(--ui-surface);
    color: var(--ui-muted);
}
.wizard-connect-card svg { color: var(--ui-secondary); }
.wizard-connect-card h3 { font-size: 1.1rem; font-weight: 800; color: var(--ui-text); margin: 0; }
.wizard-connect-card p { max-width: 34rem; margin: 0; font-size: 0.9rem; }

/* Stepper */
.wizard-stepper {
    display: flex;
    align-items: center;
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    border-radius: 1rem;
    padding: 1.1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.wizard-step {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    flex: 1;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: start;
    padding: 0;
}
.wizard-step-circle {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    font-size: 0.9rem;
    flex-shrink: 0;
    border: 2px solid var(--ui-border-strong);
    color: var(--ui-muted);
    background: var(--ui-surface-soft);
    transition: all 200ms ease;
}
.wizard-step.is-active .wizard-step-circle {
    border-color: var(--ui-secondary);
    background: var(--ui-secondary);
    color: #fff;
    box-shadow: 0 0 0 5px color-mix(in srgb, var(--ui-secondary) 16%, transparent);
}
.wizard-step.is-done .wizard-step-circle {
    border-color: var(--ui-success);
    background: color-mix(in srgb, var(--ui-success) 16%, transparent);
    color: var(--ui-success);
}
.wizard-step-text { display: flex; flex-direction: column; min-width: 0; }
.wizard-step-label { font-size: 0.85rem; font-weight: 700; color: var(--ui-text); }
.wizard-step:not(.is-active):not(.is-done) .wizard-step-label { color: var(--ui-muted); }
.wizard-step-hint { font-size: 0.72rem; color: var(--ui-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wizard-step-line { flex: 1; height: 2px; background: var(--ui-border); margin: 0 1rem; }
.wizard-step-line.is-done { background: var(--ui-success); }

.wizard-layout { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(20rem, 24rem); gap: 1.5rem; align-items: start; }

.wizard-card {
    background: var(--ui-surface);
    border: 1px solid var(--ui-border);
    border-radius: 1.1rem;
    padding: 1.75rem;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}
.wizard-card-title { font-size: 1.15rem; font-weight: 800; margin: 0 0 0.3rem; }
.wizard-card-desc { font-size: 0.85rem; color: var(--ui-muted); margin: 0 0 1.4rem; }

.wizard-picker-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: 0.9rem; }
.wizard-picker-card {
    position: relative;
    text-align: start;
    border: 2px solid var(--ui-border);
    border-radius: 0.9rem;
    padding: 1rem;
    background: var(--ui-surface-soft);
    cursor: pointer;
    transition: all 180ms ease;
}
.wizard-picker-card:hover { border-color: var(--ui-border-strong); }
.wizard-picker-card.is-selected {
    border-color: var(--ui-secondary);
    background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface));
    box-shadow: 0 8px 20px -10px color-mix(in srgb, var(--ui-secondary) 50%, transparent);
}
.wizard-picker-check {
    position: absolute; top: 0.6rem; inset-inline-start: 0.6rem;
    width: 1.3rem; height: 1.3rem; border-radius: 50%;
    background: var(--ui-secondary); color: #fff;
    display: none; align-items: center; justify-content: center;
}
.wizard-picker-card.is-selected .wizard-picker-check { display: inline-flex; }
.wizard-picker-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 2.2rem; height: 2.2rem; border-radius: 0.7rem;
    background: rgba(21, 93, 252, 0.14); color: #155dfc; margin-bottom: 0.6rem;
}
.wizard-picker-name { display: block; font-size: 0.86rem; font-weight: 700; margin-bottom: 0.2rem; color: var(--ui-text); }
.wizard-picker-cat { display: block; font-size: 0.7rem; color: var(--ui-muted); font-weight: 600; }

.wizard-empty-note { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--ui-muted); padding: 1rem; border: 1px dashed var(--ui-border); border-radius: 0.7rem; }
.wizard-inline-link { color: var(--ui-secondary); font-weight: 700; }

.wizard-var-block { border-top: 1px solid var(--ui-border); padding-top: 1.2rem; margin-top: 1.4rem; }
.wizard-var-title { font-size: 0.85rem; font-weight: 800; margin: 0 0 0.9rem; }
.wizard-var-row { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.75rem; flex-wrap: wrap; }
.wizard-var-index { font-size: 0.8rem; font-weight: 800; color: var(--ui-secondary); padding-top: 0.6rem; }
.wizard-var-button { background: var(--ui-surface-soft); border: 1px solid var(--ui-border); border-radius: 0.7rem; padding: 0.9rem; margin-bottom: 0.75rem; }
.wizard-var-button-label { font-size: 0.82rem; font-weight: 700; margin-bottom: 0.6rem; }

.wizard-upload-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0.6rem 1rem; border-radius: 0.6rem;
    background: var(--ui-surface-soft); border: 1px solid var(--ui-border);
    font-size: 0.82rem; font-weight: 600; cursor: pointer;
}
.wizard-upload-btn--error { border-color: var(--ui-danger); }

.wizard-recipient-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr)); gap: 0.8rem; }
.wizard-recipient-card {
    display: flex; align-items: center; justify-content: space-between;
    border: 2px solid var(--ui-border); border-radius: 0.75rem; padding: 0.9rem 1.1rem;
    background: var(--ui-surface-soft); cursor: pointer; transition: all 180ms ease; text-align: start;
}
.wizard-recipient-card:hover { border-color: var(--ui-border-strong); }
.wizard-recipient-card.is-selected { border-color: var(--ui-secondary); background: color-mix(in srgb, var(--ui-secondary) 8%, var(--ui-surface)); }
.wizard-recipient-name { font-size: 0.85rem; font-weight: 700; color: var(--ui-text); }
.wizard-recipient-count { font-size: 0.9rem; font-weight: 800; color: var(--ui-secondary); font-variant-numeric: tabular-nums; }

.wizard-segmented { display: flex; border: 1px solid var(--ui-border); border-radius: 0.7rem; overflow: hidden; margin-bottom: 1.2rem; width: fit-content; }
.wizard-segmented button {
    border: none; background: var(--ui-surface-soft); color: var(--ui-muted);
    font-size: 0.82rem; font-weight: 700; padding: 0.6rem 1.2rem; cursor: pointer;
}
.wizard-segmented button.is-active { background: var(--ui-secondary); color: #fff; }

.wizard-usage-box { border: 1px solid var(--ui-border); border-radius: 0.75rem; padding: 0.9rem 1.1rem; margin: 1.2rem 0; background: var(--ui-surface-soft); }
.wizard-usage-box.is-danger { border-color: color-mix(in srgb, var(--ui-danger) 45%, transparent); background: color-mix(in srgb, var(--ui-danger) 8%, var(--ui-surface-soft)); }
.wizard-usage-row { display: flex; justify-content: space-between; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.5rem; }
.wizard-usage-track { height: 6px; border-radius: 999px; background: var(--ui-border); overflow: hidden; }
.wizard-usage-fill { height: 100%; border-radius: 999px; background: var(--ui-secondary); transition: width 400ms ease; }
.wizard-usage-box.is-danger .wizard-usage-fill { background: var(--ui-danger); }
.wizard-usage-warning { margin: 0.7rem 0 0; font-size: 0.8rem; color: var(--ui-danger); font-weight: 600; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

.wizard-review-list { display: flex; flex-direction: column; gap: 0.7rem; margin: 1.2rem 0; }
.wizard-review-row { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.85rem; padding-bottom: 0.7rem; border-bottom: 1px dashed var(--ui-border); }
.wizard-review-row dt { color: var(--ui-muted); font-weight: 600; }
.wizard-review-row dd { color: var(--ui-text); font-weight: 700; margin: 0; text-align: end; }

.wizard-actions { display: flex; justify-content: space-between; margin-top: 1.6rem; }

.wizard-preview { position: sticky; top: 1.25rem; }
.wizard-preview-frame { border: 1px solid var(--ui-border); border-radius: 1.25rem; overflow: hidden; box-shadow: 0 22px 48px -20px rgba(15, 23, 42, 0.25); background: var(--ui-surface); }
.wizard-preview-topbar { display: flex; align-items: center; gap: 0.6rem; padding: 0.9rem 1.1rem; border-bottom: 1px solid var(--ui-border); }
.wizard-preview-avatar {
    width: 2.1rem; height: 2.1rem; border-radius: 50%;
    background: color-mix(in srgb, var(--ui-secondary) 20%, transparent); color: var(--ui-secondary);
    display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem;
}
.wizard-preview-title { font-size: 0.82rem; font-weight: 700; }
.wizard-preview-sub { font-size: 0.7rem; color: var(--ui-muted); }
.wizard-preview-body { padding: 2rem 1.1rem; min-height: 22rem; display: flex; align-items: flex-end; }
.wizard-preview-hint { text-align: center; font-size: 0.76rem; color: var(--ui-muted); margin-top: 0.9rem; line-height: 1.6; }

@media (max-width: 980px) {
    .wizard-layout { grid-template-columns: 1fr; }
    .wizard-preview { position: static; }
    .wizard-stepper { flex-wrap: wrap; gap: 0.75rem; }
    .wizard-step-line { display: none; }
}
</style>
