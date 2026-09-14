<template>
    <Head>
        <title>{{ $t('Forgot Password') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
        <meta name="description" :content="$t('Reset your password. We\'ll send you instructions via email.')" />
    </Head>
    <AuthLayout>
        <form @submit.prevent="submitForm()" class="flex flex-col gap-6">
            <!-- Back to login -->
            <Link href="/login" class="inline-flex w-4 h-4 text-black dark:text-white" :aria-label="$t('Back')">
                <ArrowRightIcon v-if="isRtl" class="w-4 h-4" />
                <ArrowLeftIcon v-else class="w-4 h-4" />
            </Link>

            <div class="flex flex-col gap-8">
                <!-- Title -->
                <div class="flex flex-col gap-2">
                    <h1 class="text-2xl font-semibold leading-[29.9px] text-black dark:text-white">{{ $t('Forgot Password') }}!</h1>
                    <p class="text-lg leading-9 text-[#8899aa]">
                        {{ $t('Enter your email and we\'ll send you a verification code.') }}
                    </p>
                </div>

                <!-- Success message -->
                <div v-if="props.flash?.status?.message"
                     class="p-4 rounded-xl bg-green-50 dark:bg-green-900/30 border border-green-100 dark:border-green-700">
                    <p class="text-sm text-green-700 dark:text-green-200">
                        {{ props.flash?.status?.message }}
                    </p>
                </div>

                <!-- Email field -->
                <div class="flex flex-col gap-3">
                    <label class="block text-base font-semibold text-black dark:text-white">
                        {{ $t('Email address') }}
                    </label>
                    <FormInput
                        v-model="form.email"
                        type="email"
                        :error="form.errors.email || emailRequiredError"
                        :hide-error="true"
                        :placeholder="$t('Enter your email')"
                        :hide-label="true"
                        :input-class="[
                            'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                            (form.errors.email || emailRequiredError)
                                ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                        ]"/>
                    <AuthFieldError :message="form.errors.email || emailRequiredError" />
                </div>
            </div>

            <button type="submit"
                :disabled="isLoading"
                class="relative w-full inline-flex items-center justify-center h-[54px] px-8 bg-[#25D366] text-[#04130a] rounded-2xl hover:brightness-95 transition-all duration-200 font-semibold shadow-sm hover:shadow-lg disabled:opacity-70">
                <span class="flex items-center">
                    <svg v-if="isLoading" class="animate-spin -ms-1 me-2 h-4 w-4 text-[#04130a]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ isLoading ? $t('Sending...') : $t('Next') }}
                </span>
            </button>
        </form>
    </AuthLayout>
</template>

<script setup>
    import { Head, Link, useForm } from "@inertiajs/vue3";
    import AuthLayout from '@/Components/AuthLayout.vue';
    import AuthFieldError from '@/Components/AuthFieldError.vue';
    import FormInput from '@/Components/FormInput.vue';
    import { useRtl } from '@/Composables/useRtl';
    import { computed, defineProps, ref } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { ArrowLeftIcon, ArrowRightIcon } from '@heroicons/vue/24/outline';

    const props = defineProps(['flash', 'config', 'companyConfig']);
    const { isRtl } = useRtl();
    const { t } = useI18n();
    const isLoading = ref(false);
    const hasAttemptedSubmit = ref(false);

    const form = useForm({
        email: null
    })

    const emailRequiredError = computed(() =>
        hasAttemptedSubmit.value && !form.email ? t('Email address is required.') : null
    );

    const submitForm = async () => {
        hasAttemptedSubmit.value = true;
        if (emailRequiredError.value) return;

        isLoading.value = true;
        form.post('forgot-password', {
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            }
        });
    };
</script>
