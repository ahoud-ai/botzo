<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame min-h-full">
            <div class="mx-auto flex max-w-[1680px] flex-col gap-6 p-3 md:p-4 xl:p-6">
                <Menu />

                <section class="dev-hero">
                    <div class="relative space-y-6">
                        <div class="space-y-5">
                            <div class="dev-eyebrow">
                                <span class="dev-eyebrow-dot" />
                                {{ $t('Developer Tools') }}
                            </div>

                            <div class="max-w-3xl space-y-3">
                                <h1 class="dev-title">
                                    {{ $t('Access Tokens') }}
                                </h1>
                                <p class="dev-subtitle">
                                    {{ $t('Generate and rotate tokens from one place, then use the live examples on the right to test every endpoint.') }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="dev-stat-card">
                                    <p class="dev-stat-label">{{ $t('API keys') }}</p>
                                    <div class="dev-stat-value">{{ totalTokens }}</div>
                                </div>

                                <div class="dev-stat-card">
                                    <p class="dev-stat-label">{{ $t('Live API reference') }}</p>
                                    <div class="flex items-end gap-2">
                                        <span class="dev-stat-value">{{ docsEndpointCount }}</span>
                                        <span class="dev-stat-suffix">{{ $t('Request') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-3 pt-1">
                                <button
                                    type="button"
                                    :disabled="loadIcon || !canAdd"
                                    class="dev-btn-primary"
                                    @click="generateToken"
                                >
                                    <svg v-if="!loadIcon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                        <path d="M9 12h6M12 9v6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                        <path d="M7 5h10a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                    <svg
                                        v-else
                                        xmlns="http://www.w3.org/2000/svg"
                                        width="18"
                                        height="18"
                                        viewBox="0 0 24 24"
                                        class="animate-spin"
                                    >
                                        <path fill="currentColor" d="M12 2A10 10 0 1 0 22 12A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8A8 8 0 0 1 12 20Z" opacity=".25" />
                                        <path fill="currentColor" d="M20 12h2A10 10 0 0 0 12 2V4A8 8 0 0 1 20 12Z" />
                                    </svg>
                                    <span>{{ $t('Generate API key') }}</span>
                                </button>

                                <button
                                    type="button"
                                    class="dev-btn-ghost"
                                    @click="scrollToDocs"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                        <path d="M5 6.5A2.5 2.5 0 0 1 7.5 4H16a3 3 0 0 1 3 3v10.5A2.5 2.5 0 0 0 16.5 15H8a3 3 0 0 0-3 3V6.5Zm0 0A2.5 2.5 0 0 1 7.5 9H19" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span>{{ $t('View docs') }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="dev-code-block">
                                <p class="dev-code-block-label">{{ $t('Quick start') }}</p>
                                <p class="dev-code-block-text">{{ $t('All requests use bearer authentication.') }}</p>
                                <div class="dev-code-inset">
                                    <p class="dev-code-inset-label">{{ $t('Authentication header') }}</p>
                                    <code class="dev-code-inset-value">{{ authHeaderPreview }}</code>
                                </div>
                            </div>

                            <div class="dev-ref-card">
                                <p class="dev-stat-label">{{ $t('Reference') }}</p>
                                <p class="dev-ref-text">{{ $t('All API requests should be made to:') }}</p>
                                <code class="dev-ref-value">{{ url }}</code>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="generatedApiToken" class="dev-reveal">
                    <div class="dev-reveal-bar" />
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between lg:p-6">
                        <div class="min-w-0 flex-1">
                            <h3 class="dev-reveal-title">
                                {{ generatedApiTokenAction === 'rotated' ? $t('New API key generated') : $t('API key generated') }}
                            </h3>
                            <p class="dev-reveal-text">
                                {{ $t('Copy this token now. It will not be shown again.') }}
                            </p>
                            <code class="dev-reveal-code">{{ generatedApiToken }}</code>
                        </div>

                        <button type="button" class="dev-reveal-copy" @click="copyGeneratedToken">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M8 7.5A1.5 1.5 0 0 1 9.5 6h8A1.5 1.5 0 0 1 19 7.5v10a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 8 17.5v-10Z" stroke="currentColor" stroke-width="1.8" />
                                <path d="M5 15.5V5.5A1.5 1.5 0 0 1 6.5 4H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <span>{{ isCopied('generated-token') ? $t('Copied') : $t('Copy token') }}</span>
                        </button>
                    </div>
                </section>

                <section class="dev-panel">
                    <div class="mx-auto w-full max-w-5xl">
                        <div class="dev-panel-head">
                            <div class="space-y-2">
                                <p class="dev-panel-eyebrow">{{ $t('Token vault') }}</p>
                                <h2 class="dev-panel-title">{{ $t('Access Tokens') }}</h2>
                                <p class="dev-ref-text max-w-2xl">{{ $t('Keep one active key for production and rotate compromised keys immediately.') }}</p>
                            </div>

                            <div class="dev-count-pill">
                                <span class="dev-eyebrow-dot" />
                                {{ totalTokens }} {{ $t('API keys') }}
                            </div>
                        </div>

                        <TokenTable :rows="rows" />
                    </div>
                </section>

                <section id="developer-api-docs" class="dev-panel dev-panel--tight">
                    <Documentation :apirequests="apirequests" :base-url="url" />
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from './../Layout/App.vue';
import Documentation from './Documentation.vue';
import Menu from './Menu.vue';
import TokenTable from '@/Components/Tables/TokenTable.vue';
import { useClipboard } from '@/Composables/useClipboard';

const props = defineProps({
    rows: {
        type: Object,
        required: true,
    },
    url: {
        type: String,
        default: '',
    },
    apirequests: {
        type: Array,
        default: () => [],
    },
});

const { rows, url, apirequests } = toRefs(props);

const loadIcon = ref(false);
const { copy, isCopied } = useClipboard();
const page = usePage();
const form = useForm({});

const generatedApiToken = computed(() => page.props.flash?.generated_api_token || null);
const generatedApiTokenAction = computed(() => page.props.flash?.generated_api_token_action || 'created');
const totalTokens = computed(() => rows.value?.meta?.total ?? rows.value?.data?.length ?? 0);
const docsEndpointCount = computed(() => apirequests.value.reduce((count, section) => count + (section?.value?.length ?? 0), 0));
const authHeaderPreview = 'Authorization: Bearer YOUR_API_TOKEN';

const canAdd = computed(() => {
    const permissions = page.props.permissions;

    if (!permissions || !Array.isArray(permissions)) {
        return false;
    }

    return permissions.includes('*') || permissions.includes('developer_tools.add');
});

const generateToken = () => {
    if (!canAdd.value) {
        return;
    }

    loadIcon.value = true;

    form.post('/developer-tools/access-tokens', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onFinish: () => {
            loadIcon.value = false;
        },
    });
};

const copyGeneratedToken = () => copy(generatedApiToken.value, 'generated-token');

const scrollToDocs = () => {
    document.getElementById('developer-api-docs')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
};
</script>

<style scoped>
.dev-hero {
    position: relative;
    overflow: hidden;
    border-radius: 1.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.5rem;
    box-shadow: 0 32px 80px -60px rgba(15, 23, 42, 0.35);
}

@media (min-width: 640px) {
    .dev-hero {
        padding: 1.85rem;
    }
}

.dev-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.3rem 0.8rem;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.24em;
    color: var(--ui-muted);
}

.dev-eyebrow-dot {
    width: 0.45rem;
    height: 0.45rem;
    border-radius: 999px;
    background: var(--ui-secondary);
}

.dev-title {
    font-size: 1.85rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--ui-text);
    margin: 0;
}

@media (min-width: 640px) {
    .dev-title {
        font-size: 2.15rem;
    }
}

.dev-subtitle {
    margin: 0;
    max-width: 42rem;
    font-size: 0.9rem;
    line-height: 1.7;
    color: var(--ui-muted);
}

.dev-stat-card {
    border-radius: 1.1rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.9rem 1rem;
}

.dev-stat-label {
    margin: 0;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: var(--ui-muted);
}

.dev-stat-value {
    margin-top: 0.4rem;
    font-size: 1.65rem;
    font-weight: 700;
    color: var(--ui-text);
    font-variant-numeric: tabular-nums;
}

.dev-stat-suffix {
    padding-bottom: 0.15rem;
    font-size: 0.85rem;
    color: var(--ui-muted);
}

.dev-btn-primary {
    display: inline-flex;
    min-width: 180px;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    padding: 0.75rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: #fff;
    background: var(--ui-secondary);
    transition: filter 160ms ease;
}

.dev-btn-primary:hover:not(:disabled) {
    filter: brightness(1.05);
}

.dev-btn-primary:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.dev-btn-ghost {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border-strong);
    background: var(--ui-surface-soft);
    color: var(--ui-text);
    padding: 0.72rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    transition: filter 160ms ease;
}

.dev-btn-ghost:hover {
    filter: brightness(0.97);
}

.dark .dev-btn-ghost:hover {
    filter: brightness(1.2);
}

.dev-code-block {
    border-radius: 1.25rem;
    border: 1px solid var(--ui-border-strong);
    background: #0d1420;
    color: #fff;
    padding: 1.15rem;
}

.dev-code-block-label {
    margin: 0;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #7dd3fc;
}

.dev-code-block-text {
    margin: 0.6rem 0 0;
    font-size: 0.85rem;
    line-height: 1.7;
    color: #cbd5e1;
}

.dev-code-inset {
    margin-top: 0.9rem;
    border-radius: 0.9rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.05);
    padding: 0.85rem 0.95rem;
}

.dev-code-inset-label {
    margin: 0;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    color: #cbd5e1;
}

.dev-code-inset-value {
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

.dev-ref-card {
    border-radius: 1.25rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.15rem;
}

.dev-ref-text {
    margin: 0.6rem 0 0;
    font-size: 0.85rem;
    line-height: 1.7;
    color: var(--ui-muted);
}

.dev-ref-value {
    display: block;
    margin-top: 0.9rem;
    border-radius: 0.9rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.75rem 1rem;
    font-size: 0.75rem;
    color: var(--ui-text);
    word-break: break-all;
    direction: ltr;
    unicode-bidi: isolate;
    text-align: start;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
}

.dev-reveal {
    overflow: hidden;
    border-radius: 1.5rem;
    border: 1px solid color-mix(in srgb, var(--ui-success) 45%, var(--ui-border));
    background: color-mix(in srgb, var(--ui-success) 9%, var(--ui-surface));
}

.dev-reveal-bar {
    height: 0.35rem;
    background: var(--ui-success);
    opacity: 0.85;
}

.dev-reveal-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--ui-text);
}

.dev-reveal-text {
    margin: 0.5rem 0 0;
    max-width: 40rem;
    font-size: 0.85rem;
    line-height: 1.6;
    color: var(--ui-muted);
}

.dev-reveal-code {
    display: block;
    margin-top: 0.9rem;
    border-radius: 0.9rem;
    border: 1px solid color-mix(in srgb, var(--ui-success) 40%, var(--ui-border));
    background: var(--ui-surface);
    padding: 0.75rem 1rem;
    font-size: 0.78rem;
    color: var(--ui-text);
    word-break: break-all;
    direction: ltr;
    unicode-bidi: isolate;
    text-align: start;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, monospace;
}

.dev-reveal-copy {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border-radius: 0.9rem;
    border: none;
    padding: 0.75rem 1.1rem;
    font-size: 0.88rem;
    font-weight: 600;
    color: #fff;
    background: color-mix(in srgb, var(--ui-success) 82%, #0f172a 18%);
    transition: filter 160ms ease;
}

.dev-reveal-copy:hover {
    filter: brightness(1.08);
}

.dev-panel {
    overflow: hidden;
    border-radius: 1.75rem;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface);
    padding: 1.25rem;
    box-shadow: 0 28px 80px -60px rgba(15, 23, 42, 0.35);
}

@media (min-width: 640px) {
    .dev-panel {
        padding: 1.5rem;
    }
}

.dev-panel--tight {
    padding: 1rem;
}

@media (min-width: 640px) {
    .dev-panel--tight {
        padding: 1.15rem;
    }
}

.dev-panel-head {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    border-bottom: 1px solid var(--ui-border);
    padding-bottom: 1.15rem;
    margin-bottom: 1.35rem;
}

@media (min-width: 640px) {
    .dev-panel-head {
        flex-direction: row;
        align-items: flex-end;
        justify-content: space-between;
    }
}

.dev-panel-eyebrow {
    margin: 0;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.2em;
    color: var(--ui-muted);
}

.dev-panel-title {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--ui-text);
}

.dev-count-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 999px;
    border: 1px solid var(--ui-border);
    background: var(--ui-surface-soft);
    padding: 0.5rem 0.85rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--ui-muted);
    width: fit-content;
}
</style>
