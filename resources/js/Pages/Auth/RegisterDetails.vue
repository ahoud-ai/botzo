<template>
    <Head>
        <title>{{ $t('Sign Up') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
    </Head>
    <AuthLayout>
        <div class="flex flex-col gap-8">
            <!-- Title -->
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold leading-[29.9px] text-black dark:text-white">{{ $t('Welcome!') }}</h1>
                <p class="text-lg leading-9 text-[#8899aa]">{{ $t('A few more details to finish setting up your account.') }}</p>
            </div>

            <form @submit.prevent="submitForm()" class="flex flex-col gap-8">
                <div class="flex flex-col gap-4">
                    <!-- Password -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('Password') }}</label>
                        <div class="relative">
                            <FormInput
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                :error="form.errors.password"
                                :hide-error="true"
                                :placeholder="$t('Create a password')"
                                :hide-label="true"
                                :input-class="[
                                    'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] !pe-12 text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                    form.errors.password
                                        ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                        : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                                ]"/>
                            <button type="button" @click="showPassword = !showPassword"
                                class="absolute inset-y-0 end-4 flex items-center text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-700"
                                :aria-label="showPassword ? $t('Hide password') : $t('Show password')">
                                <EyeIcon v-if="!showPassword" class="w-6 h-6" />
                                <EyeSlashIcon v-else class="w-6 h-6" />
                            </button>
                        </div>
                        <AuthFieldError :message="form.errors.password" />
                    </div>

                    <!-- Confirm password -->
                    <div class="flex flex-col gap-3">
                        <label class="block text-base font-semibold text-black dark:text-white">{{ $t('Confirm password') }}</label>
                        <div class="relative">
                            <FormInput
                                v-model="form.password_confirmation"
                                :type="showConfirmPassword ? 'text' : 'password'"
                                :error="form.errors.password_confirmation || mismatchError"
                                :hide-error="true"
                                :placeholder="$t('Confirm your password')"
                                :hide-label="true"
                                :input-class="[
                                    'h-14 !rounded-lg !border !bg-[rgba(0,0,0,0.04)] dark:!bg-[#f0f4f8] !px-4 !py-[18px] !pe-12 text-base !text-gray-900 !placeholder-[#aaaaaa] outline-none transition-all duration-200',
                                    (form.errors.password_confirmation || mismatchError)
                                        ? '!border-red-500 focus:!ring-[3px] focus:!ring-red-500/20'
                                        : '!border-transparent focus:!border-[#25D366] focus:!ring-[3px] focus:!ring-[#25D366]/20 dark:focus:!ring-[#25D366]/30 dark:focus:!shadow-[0_0_16px_-2px_rgba(37,211,102,0.35)]'
                                ]"/>
                            <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                class="absolute inset-y-0 end-4 flex items-center text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-700"
                                :aria-label="showConfirmPassword ? $t('Hide password') : $t('Show password')">
                                <EyeIcon v-if="!showConfirmPassword" class="w-6 h-6" />
                                <EyeSlashIcon v-else class="w-6 h-6" />
                            </button>
                        </div>
                        <AuthFieldError :message="form.errors.password_confirmation || mismatchError" />
                    </div>

                    <!-- Password requirements -->
                    <div class="flex flex-col items-end justify-center p-4 rounded-lg bg-[rgba(0,0,0,0.04)] dark:bg-[#f0f4f8]">
                        <div class="flex flex-col gap-3 items-end w-full">
                            <p class="text-sm font-semibold text-[#5a6b7e] text-right">{{ $t('Password Requirements') }}</p>
                            <div class="flex flex-col gap-1 items-end w-full">
                                <div v-for="req in requirements" :key="req.key" class="flex gap-2 items-center justify-end w-full">
                                    <p class="flex-1 text-xs text-right" :class="req.met ? 'text-[#25D366]' : 'text-gray-400 dark:text-gray-500'">
                                        {{ $t(req.label) }}
                                    </p>
                                    <span
                                        class="flex items-center justify-center shrink-0 w-4 h-4 rounded-full"
                                        :class="req.met ? 'bg-[#25D366]' : 'bg-transparent border border-gray-300 dark:border-gray-500'"
                                    >
                                        <CheckIcon v-if="req.met" class="w-2.5 h-2.5 text-white" stroke-width="3" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit"
                    :disabled="isLoading || !canSubmit"
                    class="relative w-full inline-flex items-center justify-center h-[54px] px-8 bg-[#25D366] text-[#04130a] rounded-2xl hover:brightness-95 transition-all duration-200 font-semibold shadow-sm hover:shadow-lg disabled:opacity-70">
                    <span class="flex items-center">
                        <svg v-if="isLoading" class="animate-spin -ms-1 me-2 h-4 w-4 text-[#04130a]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isLoading ? $t('Creating account...') : $t('Get Started') }}
                    </span>
                </button>

                <!-- Terms and Privacy Policy -->
                <p class="text-sm text-[#8899aa] text-center">
                    {{ $t('By registering, you agree to our') }}
                    <Link href="/terms-of-service" target="_blank" rel="noopener noreferrer" class="text-black dark:text-white underline hover:text-[#25D366] transition-colors">{{ $t('terms of service') }}</Link>
                    {{ $t('and') }}
                    <Link href="/privacy" target="_blank" rel="noopener noreferrer" class="text-black dark:text-white underline hover:text-[#25D366] transition-colors">{{ $t('privacy policy') }}</Link>.
                </p>
            </form>
        </div>
    </AuthLayout>
</template>

<script setup>
    import { Head, Link, useForm } from "@inertiajs/vue3";
    import AuthLayout from '@/Components/AuthLayout.vue';
    import AuthFieldError from '@/Components/AuthFieldError.vue';
    import FormInput from '@/Components/FormInput.vue';
    import { computed, defineProps, ref } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { CheckIcon, EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';

    const props = defineProps(['flash', 'companyConfig']);
    const { t } = useI18n();

    const isLoading = ref(false);
    const showPassword = ref(false);
    const showConfirmPassword = ref(false);

    const form = useForm({
        password: null,
        password_confirmation: null,
    });

    const requirements = computed(() => [
        { key: 'length', label: 'At least 8 characters', met: (form.password || '').length >= 8 },
        { key: 'uppercase', label: 'One uppercase letter', met: /[A-Z]/.test(form.password || '') },
        { key: 'special', label: 'One special character', met: /[^A-Za-z0-9]/.test(form.password || '') },
    ]);

    const mismatchError = computed(() => {
        if (!form.password_confirmation) return null;
        return form.password !== form.password_confirmation ? t('Password confirmation does not match.') : null;
    });

    const canSubmit = computed(() =>
        (form.password || '').length >= 8 &&
        !!form.password_confirmation &&
        form.password === form.password_confirmation
    );

    const submitForm = () => {
        isLoading.value = true;
        form.post('/signup/details', {
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        });
    };
</script>
