<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="mb-1 text-xl">{{ $t('Template') }}: {{ props.template.name }}</h2>
                <p class="flex items-center text-sm leading-6 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24">
                        <path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z" />
                    </svg>
                    <span class="ms-1 mt-1">{{ $t('Configure email templates') }}</span>
                </p>
            </div>
            <div class="flex items-center gap-x-2">
                <button type="button" @click="submitForm()" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Save') }}</button>
                <Link href="/admin/settings/email-templates" class="rounded-md border border-indigo-600 px-3 py-2 text-sm text-indigo-600 shadow-sm hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Back') }}</Link>
            </div>
        </div>
        <form @submit.prevent="submitForm()">
            <FormInput v-model="form.subject" :name="''" :error="form.errors.subject" :type="'text'" :class="'sm:col-span-6 mb-4'"/>

            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-gray-200 pb-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-md px-3 py-2 text-sm transition-colors"
                        :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        @click="activeTab = tab.id"
                    >
                        {{ $t(tab.label) }}
                    </button>
                </div>

                <div class="mb-4 rounded-lg border border-indigo-100 bg-indigo-50/70 p-3">
                    <h4 class="mb-2 text-sm font-semibold text-indigo-900">{{ $t('Template placeholders') }}</h4>
                    <div class="flex flex-wrap gap-2">
                        <code
                            v-for="placeholder in placeholders"
                            :key="placeholder"
                            class="rounded bg-white px-2 py-1 text-xs text-indigo-800"
                        >
                            {{ placeholder }}
                        </code>
                    </div>
                </div>

                <div v-if="activeTab === 'visual'" class="space-y-2">
                    <QuillEditor
                        v-model:content="form.body"
                        toolbar="essential"
                        contentType="html"
                        theme="snow"
                    />
                </div>

                <div v-if="activeTab === 'html'" class="space-y-2">
                    <textarea
                        v-model="form.body"
                        class="w-full rounded-lg border border-gray-300 bg-white p-3 font-mono text-sm text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                        rows="18"
                        spellcheck="false"
                    />
                </div>

                <div v-if="activeTab === 'preview'" class="space-y-3">
                    <p class="text-xs text-amber-700">{{ $t('HTML preview may differ after sanitization') }}</p>
                    <div class="max-h-[600px] overflow-auto rounded-lg border border-gray-200 bg-white p-4">
                        <div class="prose prose-sm max-w-none" v-html="previewContent" />
                    </div>
                </div>

                <p v-if="hasUnbalancedPlaceholderBraces" class="mt-3 text-sm text-amber-700">
                    {{ $t('Unbalanced placeholder braces detected') }}
                </p>

                <p v-if="form.errors.body" class="mt-3 text-sm text-red-600">{{ form.errors.body }}</p>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import { computed, ref } from "vue";
    import AppLayout from "../Layout/App.vue";
    import { Link, useForm } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FormInput from '@/Components/FormInput.vue';
    import { QuillEditor } from '@vueup/vue-quill';
    import '@vueup/vue-quill/dist/vue-quill.snow.css';
    const { t } = useI18n();

    const props = defineProps({
        template: Object,
        placeholders: {
            type: Array,
            default: () => [],
        },
    });
    const activeTab = ref('visual');
    const tabs = [
        { id: 'visual', label: t('Visual Editor') },
        { id: 'html', label: t('HTML Source') },
        { id: 'preview', label: t('Preview') },
    ];
    const form = useForm({
        _method: 'put',
        subject: props.template.subject,
        body: props.template.body,
    });

    const placeholders = computed(() => Array.isArray(props.placeholders) ? props.placeholders : []);
    const previewContent = computed(() => String(form.body || ''));
    const hasUnbalancedPlaceholderBraces = computed(() => {
        const body = String(form.body || '');
        const opens = (body.match(/{{/g) || []).length;
        const closes = (body.match(/}}/g) || []).length;

        return opens !== closes;
    });

    const submitForm = async () => {
        const url = window.location.pathname;

        form.put(url, {
            preserveScroll: true,
        });
    };
</script>

