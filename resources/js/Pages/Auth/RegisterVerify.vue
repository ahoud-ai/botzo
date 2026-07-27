<template>
    <Head>
        <title>{{ $t('Verification Code') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
    </Head>
    <AuthLayout>
        <form @submit.prevent="submitCode" class="flex flex-col gap-6">
            <!-- Back to signup -->
            <Link href="/signup" class="inline-flex w-4 h-4 text-black dark:text-white" :aria-label="$t('Back')">
                <ArrowRightIcon v-if="isRtl" class="w-4 h-4" />
                <ArrowLeftIcon v-else class="w-4 h-4" />
            </Link>

            <div class="flex flex-col gap-8">
                <!-- Title / subtitle / change email -->
                <div class="flex flex-col items-end">
                    <div class="flex flex-col gap-2 w-full">
                        <h1 class="text-2xl font-semibold leading-[29.9px] text-black dark:text-white">{{ $t('Verification Code') }}!</h1>
                        <p class="text-lg leading-9 text-[#8899aa]">{{ $t('Enter the code sent to your email.') }}</p>
                    </div>
                    <Link href="/signup" class="text-sm font-medium leading-9 text-black dark:text-white">
                        {{ $t('Want to change your email?') }}
                    </Link>
                </div>

                <!-- OTP section -->
                <div class="flex flex-col gap-3">
                    <div dir="ltr" class="relative flex gap-2 w-full" @click="focusInput" :class="{ 'animate-pulse': form.processing }">
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
                            class="flex flex-1 h-[70px] items-center justify-center rounded-2xl border-2 text-2xl font-semibold text-black dark:text-white"
                            :class="form.errors.code ? 'border-red-500' : 'border-[#1a2332] dark:border-white'"
                        >
                            <span v-if="digit">{{ digit }}</span>
                            <span v-else-if="activeSlot === index" class="w-px h-6 bg-current animate-pulse" aria-hidden="true"></span>
                        </div>
                    </div>

                    <AuthFieldError :message="form.errors.code" />

                    <div class="flex items-center justify-between py-2 text-[11px] text-[#8899aa]">
                        <button
                            type="button"
                            @click="resend"
                            :disabled="remainingSeconds > 0 || isSending"
                            class="underline disabled:opacity-50 transition-opacity"
                        >
                            {{ isSending ? $t('Sending...') : $t('Resend verification code') }}
                        </button>
                        <span>{{ formattedTimer }}</span>
                    </div>
                </div>
            </div>

            <button
                type="submit"
                :disabled="form.processing || form.code.length !== 6"
                class="relative w-full inline-flex items-center justify-center h-[54px] px-8 bg-[#25D366] text-[#04130a] rounded-2xl hover:brightness-95 transition-all duration-200 font-semibold shadow-sm hover:shadow-lg disabled:opacity-70"
            >
                <span class="flex items-center">
                    <svg v-if="form.processing" class="animate-spin -ms-1 me-2 h-4 w-4 text-[#04130a]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ form.processing ? $t('Verifying...') : $t('Confirm') }}
                </span>
            </button>
        </form>
    </AuthLayout>
</template>

<script setup>
    import { Head, Link, router, useForm } from '@inertiajs/vue3';
    import AuthLayout from '@/Components/AuthLayout.vue';
    import AuthFieldError from '@/Components/AuthFieldError.vue';
    import { useRtl } from '@/Composables/useRtl';
    import { computed, onMounted, onUnmounted, ref } from 'vue';
    import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';

    const RESEND_COOLDOWN_SECONDS = 60;

    const props = defineProps(['flash', 'companyConfig', 'email']);
    const { isRtl } = useRtl();

    const inputRef = ref(null);
    const isSending = ref(false);
    const remainingSeconds = ref(RESEND_COOLDOWN_SECONDS);
    let timerInterval = null;

    const form = useForm({ code: '', email: props.email });

    const codeSlots = computed(() => {
        const digits = String(form.code).split('');
        return Array.from({ length: 6 }, (_, i) => digits[i] ?? '');
    });

    const activeSlot = computed(() => Math.min(String(form.code).length, 5));

    const formattedTimer = computed(() => {
        const minutes = Math.floor(remainingSeconds.value / 60).toString().padStart(2, '0');
        const seconds = (remainingSeconds.value % 60).toString().padStart(2, '0');
        return `${minutes}:${seconds}`;
    });

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
        form.post('/signup/verify');
    };

    const startResendTimer = () => {
        clearInterval(timerInterval);
        remainingSeconds.value = RESEND_COOLDOWN_SECONDS;
        timerInterval = setInterval(() => {
            if (remainingSeconds.value <= 1) {
                clearInterval(timerInterval);
                remainingSeconds.value = 0;
            } else {
                remainingSeconds.value--;
            }
        }, 1000);
    };

    const resend = () => {
        if (remainingSeconds.value > 0 || isSending.value) return;
        isSending.value = true;
        router.post('/signup/verify/resend', {}, {
            preserveScroll: true,
            onFinish: () => {
                isSending.value = false;
                startResendTimer();
            },
        });
    };

    onMounted(() => {
        startResendTimer();
    });

    onUnmounted(() => {
        clearInterval(timerInterval);
    });
</script>
