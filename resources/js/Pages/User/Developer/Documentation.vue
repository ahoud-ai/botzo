<template>
    <div class="flex flex-col gap-5">
        <div class="dev-doc-hero">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="dev-doc-hero-label">{{ $t('API reference') }}</p>
                    <h3 class="dev-doc-hero-title">{{ $t('Live API reference') }}</h3>
                    <p class="dev-doc-hero-text">{{ $t('Use the same bearer token across the endpoints below and switch languages without leaving the page.') }}</p>
                </div>

                <div class="dev-doc-hero-count">
                    <div class="dev-doc-hero-count-value">{{ totalRequests }}</div>
                    <div class="dev-doc-hero-count-label">{{ $t('Request') }}</div>
                </div>
            </div>

            <div class="mt-4 grid gap-3">
                <div class="dev-doc-hero-inset">
                    <p class="dev-doc-hero-inset-label">{{ $t('Authentication header') }}</p>
                    <code class="dev-doc-hero-inset-value">{{ authHeader }}</code>
                </div>

                <div class="dev-doc-hero-inset">
                    <p class="dev-doc-hero-inset-label">{{ $t('Base URL') }}</p>
                    <code class="dev-doc-hero-inset-value">{{ baseUrl }}</code>
                </div>
            </div>
        </div>

        <div class="dev-doc-lang">
            <div class="flex items-center justify-between gap-3">
                <p class="dev-panel-eyebrow">{{ $t('Select a language') }}</p>
                <div class="dev-doc-lang-active">{{ activeLanguage.label }}</div>
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3">
                <button
                    v-for="(language, index) in languageTabs"
                    :key="language.key"
                    type="button"
                    class="dev-doc-lang-tab"
                    :class="{ 'dev-doc-lang-tab--active': selectedLanguage === index }"
                    @click="selectedLanguage = index"
                >
                    {{ $t(language.label) }}
                </button>
            </div>
        </div>

        <div v-if="hasRequests" class="space-y-5">
            <section v-for="section in apirequests" :key="section.title" class="dev-doc-section">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h4 class="dev-doc-section-title">{{ $t(section.title) }}</h4>

                    <span class="dev-doc-section-count">
                        <span class="dev-eyebrow-dot" />
                        {{ section.value.length }} {{ $t('Request') }}
                    </span>
                </div>

                <div class="space-y-3">
                    <article
                        v-for="(item, index) in section.value"
                        :key="requestId(section.title, index)"
                        class="dev-doc-request"
                    >
                        <button
                            type="button"
                            class="dev-doc-request-head"
                            :title="isOpen(requestId(section.title, index)) ? $t('Collapse request example') : $t('Open request example')"
                            @click="toggleRequest(requestId(section.title, index))"
                        >
                            <div class="min-w-0 flex items-start gap-3">
                                <span class="dev-doc-method" :class="methodClass(item.method)">
                                    {{ item.method }}
                                </span>

                                <div class="min-w-0">
                                    <div class="dev-doc-request-title">{{ $t(item.title) }}</div>
                                    <code class="dev-doc-request-route">{{ item.route }}</code>
                                </div>
                            </div>

                            <span class="dev-doc-request-chevron" :class="{ 'dev-doc-request-chevron--open': isOpen(requestId(section.title, index)) }">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none">
                                    <path d="m6 9l6 6l6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>
                        </button>

                        <div v-if="isOpen(requestId(section.title, index))" class="dev-doc-request-body">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="dev-doc-status-chip">
                                    <span class="dev-doc-status-dot" />
                                    200
                                </span>
                                <span class="dev-doc-lang-chip">{{ activeLanguage.label }}</span>
                            </div>

                            <div class="dev-doc-code-panel">
                                <div class="dev-doc-code-head">
                                    <span>{{ activeLanguage.codeLang }}</span>
                                    <button
                                        type="button"
                                        class="dev-doc-code-copy"
                                        @click.stop="copyRequestCode(item, requestId(section.title, index))"
                                    >
                                        {{ isCopied(requestId(section.title, index)) ? $t('Copied') : $t('Copy') }}
                                    </button>
                                </div>
                                <pre class="dev-doc-code-pre"><code>{{ requestCode(item) }}</code></pre>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <div v-else class="dev-doc-empty">
            {{ $t('No request examples available yet.') }}
        </div>
    </div>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue';
import { resolveApiDocExample } from '../../../Utils/apiDocumentationExamples.js';
import { useClipboard } from '@/Composables/useClipboard';

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
const { copy, isCopied } = useClipboard();

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

const copyRequestCode = (item, id) => copy(requestCode(item), id);

const methodClass = (method) => {
    const normalized = String(method || '').toLowerCase();

    if (normalized === 'post') {
        return 'dev-doc-method--success';
    }

    if (normalized === 'put') {
        return 'dev-doc-method--warning';
    }

    if (normalized === 'del' || normalized === 'delete') {
        return 'dev-doc-method--danger';
    }

    return 'dev-doc-method--info';
};
</script>

<style scoped>
.dev-doc-hero {
    overflow: hidden;
    border-radius: 1.5rem;
    background: #0d1420;
    color: #fff;
    padding: 1.25rem;
}

.dev-doc-hero-label {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: #7dd3fc;
}

.dev-doc-hero-title {
    margin: 0.5rem 0 0;
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: #fff;
}

.dev-doc-hero-text {
    margin: 0.7rem 0 0;
    font-size: 0.85rem;
    line-height: 1.7;
    color: #cbd5e1;
}

.dev-doc-hero-count {
    flex: none;
    border-radius: 0.9rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.55rem 0.75rem;
    text-align: center;
}

.dev-doc-hero-count-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #fff;
}

.dev-doc-hero-count-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #cbd5e1;
}

.dev-doc-hero-inset {
    border-radius: 0.9rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.85rem 0.95rem;
}

.dev-doc-hero-inset-label {
    margin: 0;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #cbd5e1;
}

.dev-doc-hero-inset-value {
    display: block;
    margin-top: 0.4rem;
    font-size: 0.75rem;
    color: #bae6fd;
    word-break: break-all;
    direction: ltr;
    unicode-bidi: isolate;
    text-align: start;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
}

.dev-doc-lang {
    border-radius: 1.5rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 1rem;
}

.dev-doc-lang-active {
    display: none;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 0.25rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ui-muted);
}

@media (min-width: 640px) {
    .dev-doc-lang-active {
        display: inline-flex;
    }
}

.dev-doc-lang-tab {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border-strong);
    background: var(--ui-surface);
    color: var(--ui-muted);
    padding: 0.6rem 0.75rem;
    font-size: 0.85rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.dev-doc-lang-tab:hover {
    filter: brightness(0.97);
}

.dark .dev-doc-lang-tab:hover {
    filter: brightness(1.2);
}

.dev-doc-lang-tab.dev-doc-lang-tab--active {
    border-color: var(--ui-secondary);
    background: var(--ui-secondary);
    color: #fff;
}

.dev-doc-section {
    border-radius: 1.5rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem;
}

.dev-doc-section-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    text-transform: capitalize;
    color: var(--ui-text);
}

.dev-doc-section-count {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.3rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ui-muted);
}

.dev-doc-request {
    overflow: hidden;
    border-radius: 1.25rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
}

.dev-doc-request-head {
    display: flex;
    width: 100%;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem;
    text-align: start;
    transition: background-color 160ms ease;
}

.dev-doc-request-head:hover {
    background: var(--ui-surface);
}

.dev-doc-method {
    display: inline-flex;
    min-width: 3.6rem;
    justify-content: center;
    border-radius: 999px;
    padding: 0.3rem 0.65rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}

.dev-doc-method--success {
    background: color-mix(in srgb, #fff 84%, var(--ui-success) 16%);
    color: color-mix(in srgb, var(--ui-success) 76%, #0f172a);
}

.dev-doc-method--warning {
    background: color-mix(in srgb, #fff 84%, var(--ui-warning) 16%);
    color: color-mix(in srgb, var(--ui-warning) 78%, #0f172a);
}

.dev-doc-method--danger {
    background: color-mix(in srgb, #fff 84%, var(--ui-danger) 16%);
    color: color-mix(in srgb, var(--ui-danger) 78%, #0f172a);
}

.dev-doc-method--info {
    background: color-mix(in srgb, #fff 84%, var(--ui-secondary) 16%);
    color: color-mix(in srgb, var(--ui-secondary) 78%, #0f172a);
}

.dark .dev-doc-method--success {
    background: color-mix(in srgb, var(--ui-bg) 78%, var(--ui-success) 22%);
    color: color-mix(in srgb, var(--ui-success) 70%, #fff 30%);
}

.dark .dev-doc-method--warning {
    background: color-mix(in srgb, var(--ui-bg) 78%, var(--ui-warning) 22%);
    color: color-mix(in srgb, var(--ui-warning) 70%, #fff 30%);
}

.dark .dev-doc-method--danger {
    background: color-mix(in srgb, var(--ui-bg) 78%, var(--ui-danger) 22%);
    color: color-mix(in srgb, var(--ui-danger) 70%, #fff 30%);
}

.dark .dev-doc-method--info {
    background: color-mix(in srgb, var(--ui-bg) 78%, var(--ui-secondary) 22%);
    color: color-mix(in srgb, var(--ui-secondary) 70%, #fff 30%);
}

.dev-doc-request-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--ui-text);
}

.dev-doc-request-route {
    display: block;
    margin-top: 0.25rem;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 0.75rem;
    color: var(--ui-muted);
    direction: ltr;
    unicode-bidi: isolate;
    text-align: start;
}

.dev-doc-request-chevron {
    margin-top: 0.15rem;
    display: flex;
    height: 2.25rem;
    width: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    color: var(--ui-muted);
    transition: transform 160ms ease, color 160ms ease;
}

.dev-doc-request-chevron.dev-doc-request-chevron--open {
    transform: rotate(180deg);
    color: var(--ui-text);
}

.dev-doc-request-body {
    border-top: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.dev-doc-status-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    border: 1px solid color-mix(in srgb, var(--ui-success) 45%, var(--ui-border));
    background: color-mix(in srgb, #fff 84%, var(--ui-success) 16%);
    color: color-mix(in srgb, var(--ui-success) 76%, #0f172a);
    padding: 0.3rem 0.7rem;
    font-weight: 600;
}

.dark .dev-doc-status-chip {
    background: color-mix(in srgb, var(--ui-bg) 78%, var(--ui-success) 22%);
    color: color-mix(in srgb, var(--ui-success) 70%, #fff 30%);
}

.dev-doc-status-dot {
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 999px;
    background: currentColor;
}

.dev-doc-lang-chip {
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    color: var(--ui-muted);
    padding: 0.3rem 0.7rem;
    font-weight: 600;
}

.dev-doc-code-panel {
    overflow: hidden;
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border-strong);
}

.dev-doc-code-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    background: #0d1420;
    padding: 0.65rem 0.9rem;
}

.dev-doc-code-head span {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #94a3b8;
}

.dev-doc-code-copy {
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    color: #e2e8f0;
    padding: 0.3rem 0.75rem;
    font-size: 0.72rem;
    font-weight: 700;
    transition: background-color 160ms ease;
}

.dev-doc-code-copy:hover {
    background: rgba(255, 255, 255, 0.12);
}

.dev-doc-code-pre {
    margin: 0;
    max-height: 520px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    padding: 1rem;
    font-size: 0.75rem;
    line-height: 1.65;
    color: #e2e8f0;
    background: #0d1420;
    direction: ltr;
    text-align: start;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
}

.dev-doc-empty {
    border-radius: 1.5rem;
    border: 1px dashed var(--ui-border-strong);
    background: var(--ui-surface-soft);
    padding: 1.5rem;
    text-align: center;
    font-size: 0.85rem;
    color: var(--ui-muted);
}
</style>
