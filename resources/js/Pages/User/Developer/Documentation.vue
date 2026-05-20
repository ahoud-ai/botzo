<template>
    <div class="flex flex-col gap-5">
        <div class="overflow-hidden rounded-[28px] bg-slate-950 p-5 text-white shadow-[0_30px_80px_-50px_rgba(15,23,42,0.95)]">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-sky-200/80">
                        {{ $t('API reference') }}
                    </p>
                    <h3 class="mt-2 text-2xl font-semibold tracking-tight text-white">
                        {{ $t('Live API reference') }}
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-slate-200">
                        {{ $t('Use the same bearer token across the endpoints below and switch languages without leaving the page.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-center">
                    <div class="text-xl font-semibold text-white">{{ totalRequests }}</div>
                    <div class="text-[11px] uppercase tracking-[0.18em] text-slate-300">{{ $t('Request') }}</div>
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">
                        {{ $t('Authentication header') }}
                    </p>
                    <code class="mt-2 block break-all text-xs text-sky-100">
                        {{ authHeader }}
                    </code>
                </div>

                <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-300">
                        {{ $t('Base URL') }}
                    </p>
                    <code class="mt-2 block break-all text-xs text-sky-100">
                        {{ baseUrl }}
                    </code>
                </div>
            </div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-slate-50/80 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                        {{ $t('Select a language') }}
                    </p>
                </div>
                <div class="hidden rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-medium text-slate-500 sm:inline-flex">
                    {{ activeLanguage.label }}
                </div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3">
                <button
                    v-for="(language, index) in languageTabs"
                    :key="language.key"
                    type="button"
                    class="inline-flex items-center justify-center rounded-2xl border px-3 py-2.5 text-sm font-medium transition"
                    :class="selectedLanguage === index
                        ? 'border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-900/10'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50'"
                    @click="selectedLanguage = index"
                >
                    {{ $t(language.label) }}
                </button>
            </div>
        </div>

        <div v-if="hasRequests" class="space-y-5">
            <section
                v-for="section in apirequests"
                :key="section.title"
                class="rounded-[28px] border border-slate-200 bg-white/90 p-4 shadow-[0_24px_70px_-55px_rgba(15,23,42,0.6)]"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h4 class="text-base font-semibold capitalize text-slate-900">
                            {{ $t(section.title) }}
                        </h4>
                    </div>

                    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500">
                        <span class="h-2 w-2 rounded-full bg-sky-500" />
                        {{ section.value.length }} {{ $t('Request') }}
                    </span>
                </div>

                <div class="space-y-3">
                    <article
                        v-for="(item, index) in section.value"
                        :key="requestId(section.title, index)"
                        class="overflow-hidden rounded-[24px] border border-slate-200 bg-slate-50/75"
                    >
                        <button
                            type="button"
                            class="flex w-full items-start justify-between gap-4 px-4 py-4 text-start transition hover:bg-white"
                            :title="isOpen(requestId(section.title, index)) ? $t('Collapse request example') : $t('Open request example')"
                            @click="toggleRequest(requestId(section.title, index))"
                        >
                            <div class="min-w-0 flex items-start gap-3">
                                <span
                                    class="inline-flex min-w-[58px] justify-center rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
                                    :class="methodClass(item.method)"
                                >
                                    {{ item.method }}
                                </span>

                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $t(item.title) }}
                                    </div>
                                    <code class="mt-1 block truncate text-xs text-slate-500">
                                        {{ item.route }}
                                    </code>
                                </div>
                            </div>

                            <span
                                class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-500 transition"
                                :class="isOpen(requestId(section.title, index)) ? 'rotate-180 text-slate-900' : ''"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="m6 9l6 6l6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </button>

                        <div
                            v-if="isOpen(requestId(section.title, index))"
                            class="space-y-4 border-t border-slate-200 bg-white px-4 py-4"
                        >
                            <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 font-medium text-emerald-700">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500" />
                                    200
                                </span>
                                <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 font-medium text-slate-600">
                                    {{ activeLanguage.label }}
                                </span>
                            </div>

                            <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-slate-950">
                                <div class="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
                                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                        {{ activeLanguage.codeLang }}
                                    </span>
                                    <button
                                        type="button"
                                        class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click.stop="copyRequestCode(item, requestId(section.title, index))"
                                    >
                                        {{ copiedRequestKey === requestId(section.title, index) ? $t('Copied') : $t('Copy') }}
                                    </button>
                                </div>
                                <pre class="max-h-[520px] overflow-auto whitespace-pre-wrap break-words p-4 text-xs leading-6 text-slate-100"><code>{{ requestCode(item) }}</code></pre>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div
            v-else
            class="rounded-[28px] border border-dashed border-slate-300 bg-slate-50/80 p-6 text-center text-sm text-slate-500"
        >
            {{ $t('No request examples available yet.') }}
        </div>
    </div>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue';
import { resolveApiDocExample } from '../../../Utils/apiDocumentationExamples.js';

const props = defineProps({
    apirequests: {
        type: Array,
        default: () => [],
    },
    baseUrl: {
        type: String,
        default: '',
    },
});

const { apirequests, baseUrl } = toRefs(props);

const languageTabs = [
    { key: 'curl', label: 'CURL', requestKey: 'curl', codeLang: 'bash' },
    { key: 'php', label: 'PHP', requestKey: 'php', codeLang: 'php' },
    { key: 'nodejs', label: 'NODEJS', requestKey: 'nodejs', codeLang: 'javascript' },
    { key: 'python', label: 'PYTHON', requestKey: 'python', codeLang: 'python' },
    { key: 'java', label: 'JAVA', requestKey: 'java', codeLang: 'java' },
    { key: 'ruby', label: 'RUBY', requestKey: 'ruby', codeLang: 'ruby' },
];

const selectedLanguage = ref(0);
const openRequestKey = ref(null);
const copiedRequestKey = ref(null);

const totalRequests = computed(() => apirequests.value.reduce((count, section) => count + (section?.value?.length ?? 0), 0));
const hasRequests = computed(() => totalRequests.value > 0);
const activeLanguage = computed(() => languageTabs[selectedLanguage.value] ?? languageTabs[0]);
const authHeader = 'Authorization: Bearer YOUR_API_TOKEN';

const requestId = (sectionTitle, index) => `${sectionTitle}-${index}`;

const isOpen = (id) => openRequestKey.value === id;

const toggleRequest = (id) => {
    openRequestKey.value = openRequestKey.value === id ? null : id;
};

const requestCode = (item) => resolveApiDocExample(
    item?.request?.[activeLanguage.value.requestKey] ?? '',
    baseUrl.value,
);

const copyRequestCode = async (item, id) => {
    const code = requestCode(item);

    if (!code || typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    try {
        await navigator.clipboard.writeText(code);
    } catch (error) {
        return;
    }

    copiedRequestKey.value = id;

    if (typeof window !== 'undefined') {
        window.setTimeout(() => {
            if (copiedRequestKey.value === id) {
                copiedRequestKey.value = null;
            }
        }, 1600);
    }
};

const methodClass = (method) => {
    const normalized = String(method || '').toLowerCase();

    if (normalized === 'post') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (normalized === 'put') {
        return 'bg-amber-100 text-amber-700';
    }

    if (normalized === 'del' || normalized === 'delete') {
        return 'bg-rose-100 text-rose-700';
    }

    return 'bg-sky-100 text-sky-700';
};
</script>
