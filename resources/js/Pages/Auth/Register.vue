<template>
    <Head>
        <title>{{ $t('Sign Up') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
        <meta name="description" :content="$t('Create your account to start transforming your business communication.')" />
    </Head>
    <AuthLayout>
        <div class="flex flex-col gap-8">
            <!-- Welcome text -->
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold leading-[29.9px] text-black dark:text-white">{{ $t('Welcome!') }}</h1>
                <p class="text-lg leading-9 text-[#8899aa]">{{ $t('Create your account at Botzo.') }}</p>
            </div>

            <!-- Form wrapper -->
            <form @submit.prevent="submitForm()" class="flex flex-col gap-8">
                <div class="flex flex-col gap-4">
                    <!-- First name -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('First name') }}</label>
                        <FormInput
                            v-model="form.first_name"
                            type="text"
                            :error="form.errors.first_name || firstNameRequiredError"
                            :hide-error="true"
                            :placeholder="$t('First name')"
                            :hide-label="true"
                            :input-class="[
                                'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                (form.errors.first_name || firstNameRequiredError)
                                    ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                    : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                            ]"/>
                        <AuthFieldError :message="form.errors.first_name || firstNameRequiredError" />
                    </div>

                    <!-- Last name -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('Last name') }}</label>
                        <FormInput
                            v-model="form.last_name"
                            type="text"
                            :error="form.errors.last_name || lastNameRequiredError"
                            :hide-error="true"
                            :placeholder="$t('Last name')"
                            :hide-label="true"
                            :input-class="[
                                'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                (form.errors.last_name || lastNameRequiredError)
                                    ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                    : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                            ]"/>
                        <AuthFieldError :message="form.errors.last_name || lastNameRequiredError" />
                    </div>

                    <!-- Organization name -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('Organization name') }}</label>
                        <FormInput
                            v-model="form.organization_name"
                            type="text"
                            :error="form.errors.organization_name || organizationNameRequiredError"
                            :hide-error="true"
                            :placeholder="$t('Organization name')"
                            :hide-label="true"
                            :input-class="[
                                'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                (form.errors.organization_name || organizationNameRequiredError)
                                    ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                    : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                            ]"/>
                        <AuthFieldError :message="form.errors.organization_name || organizationNameRequiredError" />
                    </div>

                    <!-- Email -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('Email address') }}</label>
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
                        <p v-if="!form.errors.email && !emailRequiredError" class="text-xs text-[#8899aa]">{{ $t('We\'ll send you a verification code via email') }}</p>
                    </div>

                    <!-- Phone -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('Phone') }}</label>
                        <FormPhoneInput
                            v-model="form.phone"
                            :allowed-countries="props.signupPhoneCountries"
                            :error="form.errors.phone || phoneRequiredError"
                            :hide-error="true"
                            :hide-label="true"
                            :wrapper-class="[
                                'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 text-base !text-gray-900 outline-none transition-all duration-200',
                                (form.errors.phone || phoneRequiredError)
                                    ? '!border-red-500 focus-within:!ring-[3px] focus-within:!ring-red-500/20'
                                    : '!border-transparent focus-within:!border-[#25D366] focus-within:!ring-[3px] focus-within:!ring-[#25D366]/20 dark:focus-within:!ring-[#25D366]/30'
                            ]"/>
                        <AuthFieldError :message="form.errors.phone || phoneRequiredError" />
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

                <!-- Continue With Container: social login + sign in prompt -->
                <div class="flex flex-col gap-3">
                    <div v-if="props.companyConfig?.allow_google_login === '1' || props.companyConfig?.allow_facebook_login === '1'" class="flex flex-col items-center gap-6">
                        <div class="flex items-center gap-3 w-full">
                            <div class="flex-1 h-px bg-[#8899aa]"></div>
                            <span class="text-xs font-medium text-[#8899aa] whitespace-nowrap">{{ $t('or continue with') }}</span>
                            <div class="flex-1 h-px bg-[#8899aa]"></div>
                        </div>
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <button v-if="props.companyConfig?.allow_google_login === '1'" type="button" @click="handleGoogleLogin"
                                class="inline-flex items-center justify-center h-[54px] w-[168px] rounded-2xl border border-black dark:border-white text-black dark:text-white font-semibold hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                {{ $t('Google') }}
                            </button>

                            <button v-if="props.companyConfig?.allow_facebook_login === '1'" type="button" @click="handleFacebookLogin"
                                class="inline-flex items-center justify-center h-[54px] w-[168px] rounded-2xl border border-black dark:border-white text-black dark:text-white font-semibold hover:bg-gray-50 dark:hover:bg-white/5 transition-colors">
                                {{ $t('Facebook') }}
                            </button>
                        </div>
                    </div>

                    <!-- Sign in prompt -->
                    <div class="flex items-center justify-center gap-1">
                        <span class="text-sm text-[#8899aa]">{{ $t('Already have an account?') }}</span>
                        <Link href="/login" class="text-base font-semibold text-black dark:text-white hover:text-[#25D366] dark:hover:text-white/80 transition-colors">
                            {{ $t('Sign in') }}!
                        </Link>
                    </div>
                </div>
            </form>
        </div>
    </AuthLayout>
</template>

<script setup>
    import { Head, Link, useForm } from "@inertiajs/vue3";
    import AuthLayout from '@/Components/AuthLayout.vue';
    import AuthFieldError from '@/Components/AuthFieldError.vue';
    import FormInput from '@/Components/FormInput.vue';
    import FormPhoneInput from '@/Components/FormPhoneInput.vue';
    import { computed, defineProps, ref } from 'vue';
    import { useI18n } from 'vue-i18n';

    const props = defineProps(['flash', 'config', 'companyConfig', 'signupPhoneCountries']);
    const { t } = useI18n();

    const isLoading = ref(false);
    const hasAttemptedSubmit = ref(false);

    const form = useForm({
        first_name: null,
        last_name: null,
        organization_name: null,
        email: null,
        phone: null,
    });

    const requiredError = (field, message) => computed(() =>
        hasAttemptedSubmit.value && !form[field] ? t(message) : null
    );

    const firstNameRequiredError = requiredError('first_name', 'First name is required.');
    const lastNameRequiredError = requiredError('last_name', 'Last name is required.');
    const organizationNameRequiredError = requiredError('organization_name', 'Organization name is required.');
    const emailRequiredError = requiredError('email', 'Email address is required.');
    const phoneRequiredError = requiredError('phone', 'Phone number is required');

    const submitForm = () => {
        hasAttemptedSubmit.value = true;
        if (
            firstNameRequiredError.value ||
            lastNameRequiredError.value ||
            organizationNameRequiredError.value ||
            emailRequiredError.value ||
            phoneRequiredError.value
        ) return;

        isLoading.value = true;
        form.post('/signup', {
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        });
    };

    const handleGoogleLogin = () => {
        window.location.href = '/social-login/google';
    };

    const handleFacebookLogin = () => {
        window.location.href = '/social-login/facebook';
    };
</script>
