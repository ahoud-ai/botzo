<template>
    <Head>
        <title>{{ $t('Login') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
        <meta name="description" :content="$t('Sign in to your account to access your dashboard and manage your business communication.')" />
    </Head>
    <AuthLayout>
                    <!-- Welcome text -->
                    <div class="flex flex-col gap-2">
                        <h1 class="text-2xl font-semibold leading-[29.9px] text-black dark:text-white">{{ $t('Welcome back') }}</h1>
                        <p class="text-lg leading-9 text-[#8899aa]">
                            {{ $t('Sign in to your account to continue.') }}
                        </p>
                    </div>

                    <!-- Form wrapper -->
                    <form @submit.prevent="submitForm()" class="flex flex-col gap-8">
                        <!-- Login form fields -->
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-col gap-3">
                                <label class="block text-base font-semibold text-black dark:text-white">
                                    {{ $t('Email') }}
                                </label>
                                <FormInput
                                    v-model="form.email"
                                    type="email"
                                    :error="form.errors.email || emailRequiredError"
                                    :hide-error="true"
                                    :placeholder="$t('Enter your email address')"
                                    :hide-label="true"
                                    :input-class="[
                                        'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                        (form.errors.email || emailRequiredError)
                                            ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                            : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                                    ]"/>
                                <AuthFieldError :message="form.errors.email || emailRequiredError" />
                            </div>

                            <div class="flex flex-col gap-3">
                                <div class="flex flex-col gap-3">
                                    <label class="block text-base font-semibold text-black dark:text-white">
                                        {{ $t('Password') }}
                                    </label>
                                    <div class="relative">
                                        <FormInput
                                            v-model="form.password"
                                            :type="showPassword ? 'text' : 'password'"
                                            :error="form.errors.password || passwordRequiredError"
                                            :hide-error="true"
                                            :placeholder="$t('Enter your password')"
                                            :hide-label="true"
                                            :input-class="[
                                                'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] !pe-12 text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                                (form.errors.password || passwordRequiredError)
                                                    ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                                    : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                                            ]"/>
                                        <button
                                            type="button"
                                            @click="showPassword = !showPassword"
                                            class="absolute inset-y-0 end-4 flex items-center text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-700"
                                            :aria-label="showPassword ? $t('Hide password') : $t('Show password')"
                                        >
                                            <EyeIcon v-if="!showPassword" class="w-6 h-6" />
                                            <EyeSlashIcon v-else class="w-6 h-6" />
                                        </button>
                                    </div>
                                    <AuthFieldError :message="form.errors.password || passwordRequiredError" />
                                </div>

                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center">
                                        <label for="remember" class="relative flex items-center cursor-pointer group">
                                            <input
                                                id="remember"
                                                type="checkbox"
                                                v-model="form.remember"
                                                class="sr-only"
                                            >
                                            <div :class="[
                                                'relative w-5 h-5 border rounded transition-all duration-200 ease-in-out flex items-center justify-center',
                                                form.remember
                                                    ? 'bg-[#25D366] border-[#25D366]'
                                                    : 'bg-white dark:bg-transparent border-[#1a2332] dark:border-white'
                                            ]">
                                                <svg
                                                    v-if="form.remember"
                                                    class="w-3.5 h-3.5 text-white transition-opacity duration-200 pointer-events-none"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke="currentColor"
                                                    stroke-width="3"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                            <span class="ms-3 font-medium text-slate-400 group-hover:text-gray-900 dark:group-hover:text-gray-200 transition-colors">
                                                {{ $t('Remember me') }}
                                            </span>
                                        </label>
                                    </div>

                                    <Link href="/forgot-password"
                                        class="font-semibold text-slate-400 hover:text-[#25D366] dark:hover:text-white transition-colors">
                                        {{ $t('Forgot password?') }}
                                    </Link>
                                </div>
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
                                {{ isLoading ? $t('Signing in...') : $t('Sign in') }}
                            </span>
                        </button>

                        <!-- Continue With Container: social login + signup prompt -->
                        <div class="flex flex-col gap-3">
                            <div v-if="props.companyConfig?.allow_google_login === '1' || props.companyConfig?.allow_facebook_login === '1'" class="flex flex-col items-center gap-6">
                                <div class="flex items-center gap-3 w-full">
                                    <div class="flex-1 h-px bg-[#8899aa]"></div>
                                    <span class="text-xs font-medium text-[#8899aa] whitespace-nowrap">
                                        {{ $t('or continue with') }}
                                    </span>
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

                            <!-- Sign up prompt -->
                            <div class="flex items-center justify-center gap-1">
                                <span class="text-sm text-[#8899aa]">{{ $t('Don\'t have an account?') }}</span>
                                <Link href="/signup" class="text-base font-semibold text-black dark:text-white hover:text-[#25D366] dark:hover:text-white/80 transition-colors">
                                    {{ $t('Create one here') }}
                                </Link>
                            </div>
                        </div>
                    </form>
    </AuthLayout>
</template>

<script setup>
    import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
    import AuthLayout from '@/Components/AuthLayout.vue';
    import AuthFieldError from '@/Components/AuthFieldError.vue';
    import FormInput from '@/Components/FormInput.vue';
    import { computed, defineProps, ref, onMounted, watch } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { toast } from 'vue3-toastify';
    import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

    const props = defineProps(['flash', 'config', 'companyConfig']);
    const page = usePage();
    const { t } = useI18n();
    const isLoading = ref(false);
    const showPassword = ref(false);
    const hasAttemptedSubmit = ref(false);

    const form = useForm({
        email: null,
        password: null,
        remember: false
    })

    const emailRequiredError = computed(() =>
        hasAttemptedSubmit.value && !form.email ? t('Email address is required.') : null
    );
    const passwordRequiredError = computed(() =>
        hasAttemptedSubmit.value && !form.password ? t('Password is required.') : null
    );

    const submitForm = async (event) => {
        hasAttemptedSubmit.value = true;
        if (emailRequiredError.value || passwordRequiredError.value) return;

        isLoading.value = true;
        form.post('/login', {
            onSuccess: () => form.reset(),
            onFinish: () => {
                isLoading.value = false;
            }
        });
    };

    const handleGoogleLogin = () => {
        window.location.href = '/social-login/google';
    };

    const handleFacebookLogin = () => {
        window.location.href = '/social-login/facebook';
    };
    const showFlashToast = () => {
        const status = page.props.flash?.status;

        if (status?.message) {
            toast(status.message, {
                autoClose: 3000,
                type: status.type === 'error' ? 'error' : undefined
    });
        }
    };

    onMounted(() => {
        if (window.location.hash === '#_=_') {
            window.history.replaceState(null, document.title, window.location.pathname + window.location.search);
        }
        showFlashToast();
    });
    watch(() => page.props.flash?.status, showFlashToast, { deep: true });
</script>

