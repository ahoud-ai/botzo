<template>
    <AppLayout>
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="mb-1 text-xl">{{ $t('Content pages') }} | {{ $t('Edit') }}</h2>
                <p class="flex items-center text-sm leading-6 text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11v5m0 5a9 9 0 1 1 0-18a9 9 0 0 1 0 18Zm.05-13v.1h-.1V8h.1Z"/></svg>
                    <span class="ms-1 mt-1">{{ $t('Edit bilingual public pages using the advanced editor') }}</span>
                </p>
            </div>
            <div class="flex items-center gap-x-2">
                <button type="button" @click="submitForm()" class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Save') }}</button>
                <Link href="/admin/settings/pages" class="rounded-md border border-indigo-600 px-3 py-2 text-sm text-indigo-600 shadow-sm hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">{{ $t('Back') }}</Link>
                <button type="button" @click="deletePage()" class="rounded-md border border-red-500 px-3 py-2 text-sm text-red-600 shadow-sm hover:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500">{{ $t('Delete') }}</button>
            </div>
        </div>

        <form @submit.prevent="submitForm()" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormInput v-model="form.name_ar" :name="$t('Name (Arabic)')" :error="form.errors.name_ar" :type="'text'" :class="'sm:col-span-1'"/>
                <FormInput v-model="form.name_en" :name="$t('Name (English)')" :error="form.errors.name_en" :type="'text'" :class="'sm:col-span-1'"/>
            </div>

            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                <div class="flex flex-wrap gap-3">
                    <span class="rounded bg-white px-2 py-1">{{ `${$t('Slug')} (AR): /pages/${formattedNameAr || '-'}` }}</span>
                    <span class="rounded bg-white px-2 py-1">{{ `${$t('Slug')} (EN): /pages/${formattedNameEn || '-'}` }}</span>
                    <span class="rounded bg-white px-2 py-1">{{ `${$t('Canonical slug')}: /pages/${canonicalSlug || '-'}` }}</span>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-gray-200 pb-3">
                    <button
                        v-for="locale in localeTabs"
                        :key="locale.id"
                        type="button"
                        class="rounded-md px-3 py-2 text-sm transition-colors"
                        :class="activeLocale === locale.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        @click="activeLocale = locale.id"
                    >
                        {{ locale.label }}
                    </button>
                </div>

                <div class="mb-4 flex flex-wrap items-center gap-2 border-b border-gray-200 pb-3">
                    <button
                        v-for="tab in editorTabs"
                        :key="tab.id"
                        type="button"
                        class="rounded-md px-3 py-2 text-sm transition-colors"
                        :class="activeTab === tab.id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div v-if="activeTab === 'visual'" class="space-y-2">
                    <QuillEditor
                        v-model:content="selectedContent"
                        toolbar="essential"
                        contentType="html"
                        theme="snow"
                    />
                </div>

                <div v-if="activeTab === 'html'" class="space-y-2">
                    <textarea
                        v-model="selectedContent"
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

                <p v-if="currentContentError" class="mt-3 text-sm text-red-600">{{ currentContentError }}</p>
                <p v-if="form.errors.content" class="mt-1 text-sm text-red-600">{{ form.errors.content }}</p>
            </div>
        </form>
    </AppLayout>
</template>
<script setup>
    import AppLayout from "../Layout/App.vue";
    import { computed, ref } from 'vue';
    import { Link, useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import { useI18n } from 'vue-i18n';
    import { QuillEditor } from '@vueup/vue-quill';
    import '@vueup/vue-quill/dist/vue-quill.snow.css';

    const { t } = useI18n();
    const props = defineProps({ page: Object });
    const activeLocale = ref('ar');
    const activeTab = ref('visual');

    const localeTabs = [
        { id: 'ar', label: t('Arabic') },
        { id: 'en', label: t('English') },
    ];

    const editorTabs = [
        { id: 'visual', label: t('Visual Editor') },
        { id: 'html', label: t('HTML Source') },
        { id: 'preview', label: t('Preview') },
    ];

    const form = useForm({
        _method: 'put',
        name: props.page.name ?? null,
        name_ar: props.page.name_ar ?? props.page.name ?? null,
        name_en: props.page.name_en ?? props.page.name ?? null,
        content: props.page.content ?? null,
        content_ar: props.page.content_ar ?? props.page.content ?? null,
        content_en: props.page.content_en ?? props.page.content ?? null,
    });

    const firstFilled = (...values) => {
        for (const value of values) {
            if (typeof value === 'string' && value.trim() !== '') {
                return value;
            }
        }

        return null;
    };

    const toSlug = (value) => String(value || '').trim().toLowerCase().replace(/\s+/g, '-');

    const selectedContent = computed({
        get() {
            return activeLocale.value === 'ar' ? form.content_ar : form.content_en;
        },
        set(value) {
            if (activeLocale.value === 'ar') {
                form.content_ar = value;
                return;
            }

            form.content_en = value;
        },
    });

    const previewContent = computed(() => String(selectedContent.value || ''));
    const currentContentError = computed(() => activeLocale.value === 'ar' ? form.errors.content_ar : form.errors.content_en);

    const formattedNameAr = computed(() => toSlug(form.name_ar));
    const formattedNameEn = computed(() => toSlug(form.name_en));
    const canonicalSlug = computed(() => toSlug(firstFilled(form.name_en, form.name_ar, form.name)));

    const submitForm = async () => {
        form.name = firstFilled(form.name_en, form.name_ar, form.name);
        form.content = firstFilled(form.content_en, form.content_ar, form.content);

        const url = window.location.pathname;

        form.put(url, {
            preserveScroll: true,
        });
    };

    const deletePage = async () => {
        const url = window.location.pathname;

        form.delete(url, {
            onBefore: () => confirm(t('Are you sure you want to delete this page?')),
            preserveScroll: true,
        });
    };
</script>

