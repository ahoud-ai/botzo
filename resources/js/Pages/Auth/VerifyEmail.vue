<template>
    <div :class="rtlClass">
        <div class="flex min-h-[100svh] items-start justify-center bg-white px-4 py-16 sm:py-24">
            <div class="w-full max-w-[26rem]">
                <div class="rounded-xl border border-slate-100 bg-white px-5 py-7 shadow-sm sm:px-7">
                    <div class="flex justify-center mb-6">
                        <Link href="/">
                            <img class="max-w-[180px]" v-if="props.companyConfig.logo" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                            <h4 v-else class="text-3xl font-bold text-slate-950">{{ props.companyConfig.company_name || 'Botzo' }}</h4>
                        </Link>
                    </div>
                    <h1 class="text-center text-2xl font-semibold text-slate-950">{{ $t('Verify your email') }}</h1>
                    <div class="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-center text-sm leading-6 text-slate-600">{{ $t('We\'ve sent you a verification code to your email. Enter it to activate your account.') }}</div>
                    <form class="mt-5 space-y-4" @submit.prevent="verifyCode">
                        <label class="block">
                            <span class="sr-only">{{ $t('Verification Code') }}</span>
                            <div
                                dir="ltr"
                                class="relative grid grid-cols-6 gap-2"
                                :class="{ 'animate-pulse': form.processing }"
                                @click="focusCodeInput"
                            >
                                <input
                                    ref="codeInputRef"
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
                                    class="flex aspect-square min-h-12 items-center justify-center rounded-lg border bg-white text-2xl font-semibold text-slate-950 shadow-sm transition sm:min-h-14"
                                    :class="[
                                        form.errors.code ? 'border-red-400' : 'border-slate-200',
                                        activeSlotIndex === index && !form.errors.code ? 'border-primary-500 ring-2 ring-primary-100' : ''
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
                            {{ form.processing ? $t('Processing') : $t('Activate account') }}
                        </button>
                    </form>
                    <div class="mt-5 text-center text-sm text-slate-500">
                        <span v-if="isSending" class="inline-flex items-center justify-center gap-x-2">
                            {{ $t('Processing') }}
                            <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-900"></span>
                        </span>
                        <button
                            v-else
                            type="button"
                            @click="resendEmail"
                            :disabled="isSending"
                            class="text-sm text-primary-600 underline-offset-4 hover:underline disabled:opacity-70"
                        >
                            {{ $t('Email not received?') }} {{ $t('Resend email') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
    import { Link, router, useForm, usePage } from "@inertiajs/vue3";
    import { computed, defineProps, ref } from 'vue';
    import { toast } from 'vue3-toastify';
    import 'vue3-toastify/dist/index.css';
    import { useRtl } from '@/Composables/useRtl';

    const { rtlClass, isRtl } = useRtl();

    const props = defineProps(['flash', 'config', 'companyConfig']);
    const isSending = ref(false);
    const form = useForm({
        code: '',
    });
    const codeInputRef = ref(null);
    const codeSlots = computed(() => {
        const digits = String(form.code).split('');

        return Array.from({ length: 6 }, (_, index) => digits[index] ?? '');
    });
    const activeSlotIndex = computed(() => Math.min(String(form.code).length, 5));

    const normalizeCode = (value) => String(value).replace(/\D/g, '').slice(0, 6);

    const focusCodeInput = () => {
        codeInputRef.value?.focus();
    };

    const updateCode = (event) => {
        form.code = normalizeCode(event.target.value);
        event.target.value = form.code;
    };

    const handlePaste = (event) => {
        event.preventDefault();
        form.code = normalizeCode(event.clipboardData?.getData('text') ?? '');
    };

    const verifyCode = () => {
        form.code = normalizeCode(form.code);

        if (form.code.length !== 6) {
            return;
        }

        form.post('/email/verify-code');
    };

    const resendEmail = () => {
        isSending.value = true;
        router.visit('/email/verification-notification', {
            method: 'post',
            data: {},
            onFinish: () => {
                isSending.value = false;
                showToast(usePage().props.flash.status?.message ?? '');
            },
        });
    };

    const showToast = (message) => {
        if (!message) {
            return;
        }

        toast(message, {
            autoClose: 3000,
        });
    };
</script>
