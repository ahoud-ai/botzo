<template>
    <AppLayout>
        <div class="ui-page ui-fade-up ui-page-frame min-h-full">
            <div class="mx-auto flex max-w-[1680px] flex-col gap-6 p-3 md:p-4 xl:p-6">
                <Menu />

                <section class="relative overflow-hidden rounded-[32px] border border-slate-200/70 bg-white/85 p-5 shadow-[0_40px_100px_-60px_rgba(15,23,42,0.7)] backdrop-blur-xl sm:p-7">
                    <div class="pointer-events-none absolute inset-y-0 start-0 w-52 bg-[radial-gradient(circle_at_center,rgba(14,165,233,0.18),transparent_70%)]" />
                    <div class="pointer-events-none absolute -top-16 end-0 h-40 w-40 rounded-full bg-emerald-200/35 blur-3xl" />
                    <div class="pointer-events-none absolute bottom-0 end-20 h-28 w-28 rounded-full bg-indigo-200/25 blur-3xl" />

                    <div class="relative space-y-6">
                        <div class="space-y-5">
                            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/75 px-3 py-1 text-xs font-semibold uppercase tracking-[0.28em] text-slate-500">
                                <span class="h-2 w-2 rounded-full bg-emerald-500" />
                                {{ $t('Developer Tools') }}
                            </div>

                            <div class="max-w-3xl space-y-3">
                                <h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl">
                                    {{ $t('Access Tokens') }}
                                </h1>
                                <p class="max-w-2xl text-sm leading-7 text-slate-600 sm:text-base">
                                    {{ $t('Generate and rotate tokens from one place, then use the live examples on the right to test every endpoint.') }}
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div class="rounded-[24px] border border-slate-200/80 bg-white/80 p-4 shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                        {{ $t('API keys') }}
                                    </p>
                                    <div class="mt-2 text-3xl font-semibold text-slate-950">
                                        {{ totalTokens }}
                                    </div>
                                </div>

                                <div class="rounded-[24px] border border-slate-200/80 bg-white/80 p-4 shadow-sm">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                        {{ $t('Live API reference') }}
                                    </p>
                                    <div class="mt-2 flex items-end gap-2">
                                        <span class="text-3xl font-semibold text-slate-950">{{ docsEndpointCount }}</span>
                                        <span class="pb-1 text-sm text-slate-500">{{ $t('Request') }}</span>
                                    </div>
                                </div>

                            </div>

                            <div class="flex flex-wrap items-center gap-3 pt-1">
                                <button
                                    type="button"
                                    :disabled="loadIcon || !canAdd"
                                    class="inline-flex min-w-[180px] items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-900/15 transition disabled:cursor-not-allowed disabled:opacity-60"
                                    :class="canAdd ? 'bg-slate-900 hover:bg-slate-800' : 'bg-slate-400'"
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
                                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
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
                            <div class="rounded-[28px] border border-slate-200/80 bg-slate-950 p-5 text-white shadow-[0_28px_70px_-42px_rgba(15,23,42,0.95)]">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-sky-200/80">
                                    {{ $t('Quick start') }}
                                </p>
                                <p class="mt-3 text-sm leading-7 text-slate-200">
                                    {{ $t('All requests use bearer authentication.') }}
                                </p>
                                <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-300">
                                        {{ $t('Authentication header') }}
                                    </p>
                                    <code class="mt-2 block break-all text-xs text-sky-100">
                                        {{ authHeaderPreview }}
                                    </code>
                                </div>
                            </div>

                            <div class="rounded-[28px] border border-slate-200/80 bg-white/80 p-5 shadow-sm">
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-400">
                                    {{ $t('Reference') }}
                                </p>
                                <p class="mt-3 text-sm leading-7 text-slate-600">
                                    {{ $t('All API requests should be made to:') }}
                                </p>
                                <code class="mt-4 block break-all rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700">
                                    {{ url }}
                                </code>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-if="generatedApiToken"
                    class="overflow-hidden rounded-[28px] border border-emerald-200 bg-emerald-50 shadow-[0_24px_70px_-50px_rgba(16,185,129,0.8)]"
                >
                    <div class="h-1.5 bg-gradient-to-r from-emerald-500 via-teal-500 to-sky-500" />
                    <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between lg:p-6">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-base font-semibold text-emerald-950">
                                {{ generatedApiTokenAction === 'rotated' ? $t('New API key generated') : $t('API key generated') }}
                            </h3>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-emerald-900/80">
                                {{ $t('Copy this token now. It will not be shown again.') }}
                            </p>
                            <code class="mt-4 block break-all rounded-2xl border border-emerald-200 bg-white px-4 py-3 text-xs text-slate-900">
                                {{ generatedApiToken }}
                            </code>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 self-start rounded-2xl bg-emerald-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800"
                            @click="copyGeneratedToken"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M8 7.5A1.5 1.5 0 0 1 9.5 6h8A1.5 1.5 0 0 1 19 7.5v10a1.5 1.5 0 0 1-1.5 1.5h-8A1.5 1.5 0 0 1 8 17.5v-10Z" stroke="currentColor" stroke-width="1.8" />
                                <path d="M5 15.5V5.5A1.5 1.5 0 0 1 6.5 4H15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                            </svg>
                            <span>{{ copiedGeneratedToken ? $t('Copied') : $t('Copy token') }}</span>
                        </button>
                    </div>
                </section>

                <section class="overflow-hidden rounded-[32px] border border-slate-200/70 bg-white/90 p-5 shadow-[0_32px_90px_-60px_rgba(15,23,42,0.75)] backdrop-blur-xl sm:p-6">
                    <div class="mx-auto w-full max-w-5xl">
                        <div class="mb-6 flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
                            <div class="space-y-2">
                                <p class="text-xs font-semibold uppercase tracking-[0.26em] text-slate-400">
                                    {{ $t('Token vault') }}
                                </p>
                                <h2 class="text-2xl font-semibold text-slate-950">
                                    {{ $t('Access Tokens') }}
                                </h2>
                                <p class="max-w-2xl text-sm leading-7 text-slate-600">
                                    {{ $t('Keep one active key for production and rotate compromised keys immediately.') }}
                                </p>
                            </div>

                            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-600">
                                <span class="h-2.5 w-2.5 rounded-full bg-sky-500" />
                                {{ totalTokens }} {{ $t('API keys') }}
                            </div>
                        </div>

                        <TokenTable :rows="rows" />
                    </div>
                </section>

                <section id="developer-api-docs" class="overflow-hidden rounded-[32px] border border-slate-200/70 bg-white/90 p-4 shadow-[0_32px_90px_-60px_rgba(15,23,42,0.75)] backdrop-blur-xl sm:p-5">
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
const copiedGeneratedToken = ref(false);
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

const copyGeneratedToken = async () => {
    if (!generatedApiToken.value) {
        return;
    }

    if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(generatedApiToken.value);
    } else {
        const tempInput = document.createElement('textarea');
        tempInput.value = generatedApiToken.value;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
    }

    copiedGeneratedToken.value = true;

    window.setTimeout(() => {
        copiedGeneratedToken.value = false;
    }, 2000);
};

const scrollToDocs = () => {
    document.getElementById('developer-api-docs')?.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
    });
};
</script>
