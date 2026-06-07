<template>
    <Head>
        <title>{{ $t('Verify Reset Code') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
    </Head>
    <div class="fixed top-4 end-4 z-10 text-gray-600 dark:text-gray-300">
        <LangToggle :languages="languages" :currentLanguage="currentLanguage" />
    </div>
    <div class="flex min-h-[100svh] items-start justify-center bg-white dark:bg-gray-900 px-4 py-16 sm:py-24">
        <div class="w-full max-w-[26rem]">
            <div class="rounded-xl border border-slate-100 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-7 shadow-sm sm:px-7">
                <div class="flex justify-center mb-6">
                    <Link href="/">
                        <img class="max-w-[180px]" v-if="props.companyConfig?.logo" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                        <h4 v-else class="text-3xl font-bold text-slate-950 dark:text-white">{{ props.companyConfig?.company_name || 'Botzo' }}</h4>
                    </Link>
                </div>

                <h1 class="text-center text-2xl font-semibold text-slate-950 dark:text-white">{{ $t('Check your email') }}</h1>
                <div class="mt-4 rounded-lg bg-slate-50 dark:bg-gray-700 px-4 py-3 text-center text-sm leading-6 text-slate-600 dark:text-slate-300">
                    {{ $t("We've sent a 6-digit reset code to") }} <strong>{{ props.email }}</strong>
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="submitCode">
                    <label class="block">
                        <span class="sr-only">{{ $t('Reset Code') }}</span>
                        <div
                            dir="ltr"
                            class="relative grid grid-cols-6 gap-2"
                            :class="{ 'animate-pulse': form.processing }"
                            @click="focusInput"
                        >
                            <input
                                ref="inputRef"
                                :value="form.code"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                autocomplete="one-time-code"
                                class="absolute inset-0 h-full w-full cursor-text opacity-0"
                                @input="updateCode"
                                @paste="handlePaste"
                            >
                            <div
                                v-for="(digit, index) in codeSlots"
                                :key="index"
                                class="flex aspect-square min-h-12 items-center justify-center rounded-lg border bg-white dark:bg-gray-700 text-2xl font-semibold text-slate-950 dark:text-white shadow-sm transition sm:min-h-14"
                                :class="[
                                    form.errors.code ? 'border-red-400' : 'border-slate-200 dark:border-gray-600',
                                    activeSlot === index && !form.errors.code ? 'border-primary ring-2 ring-primary/20' : ''
                                ]"
                            >
                                {{ digit }}
                            </div>
                        </div>
                    </label>

                    <div v-if="form.errors.code" class="text-center text-sm text-red-600">{{ form.errors.code }}</div>

                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length !== 6"
                        class="w-full rounded-lg bg-primary px-4 py-3.5 text-sm font-semibold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? $t('Verifying...') : $t('Verify & continue') }}
                    </button>
                </form>

                <div class="mt-5 text-center text-sm text-slate-500 dark:text-slate-400">
                    <span v-if="isSending" class="inline-flex items-center gap-x-2">
                        {{ $t('Sending...') }}
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-900"></span>
                    </span>
                    <button
                        v-else
                        type="button"
                        @click="resend"
                        :disabled="isSending"
                        class="underline-offset-4 hover:underline disabled:opacity-70"
                    >
                        {{ $t("Didn't receive it?") }} {{ $t('Resend code') }}
                    </button>
                </div>

                <div class="mt-4 text-center">
                    <Link href="/forgot-password" class="text-sm text-primary hover:underline">
                        &larr; {{ $t('Back') }}
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import LangToggle from '@/Components/LangToggle.vue';
import { computed, ref } from 'vue';

const props = defineProps(['flash', 'companyConfig', 'email']);
const page = usePage();
const languages = computed(() => page.props.languages);
const currentLanguage = computed(() => page.props.currentLanguage);

const inputRef = ref(null);
const isSending = ref(false);

const form = useForm({ code: '', email: props.email });

const codeSlots = computed(() => {
    const digits = String(form.code).split('');
    return Array.from({ length: 6 }, (_, i) => digits[i] ?? '');
});

const activeSlot = computed(() => Math.min(String(form.code).length, 5));

const normalize = (val) => String(val).replace(/\D/g, '').slice(0, 6);

const focusInput = () => inputRef.value?.focus();

const updateCode = (e) => {
    form.code = normalize(e.target.value);
    e.target.value = form.code;
};

const handlePaste = (e) => {
    e.preventDefault();
    form.code = normalize(e.clipboardData?.getData('text') ?? '');
};

const submitCode = () => {
    form.code = normalize(form.code);
    if (form.code.length !== 6) return;
    form.post('/forgot-password/verify');
};

const resend = () => {
    isSending.value = true;
    router.visit('/forgot-password', {
        method: 'post',
        data: { email: props.email },
        onFinish: () => { isSending.value = false; },
    });
};
</script>
