<template>
    <Head>
        <title>{{ $t('Reset Password') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
    </Head>
    <div class="min-h-[100svh] bg-white dark:bg-gray-900">
        <div class="flex flex-col justify-between min-h-[100svh] mx-4 sm:mx-8 md:mx-10">
            <div class="flex justify-between items-center mt-8 md:mt-10 mb-4">
                <Link href="/" class="inline-block">
                    <img v-if="props.companyConfig?.logo" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name" class="max-w-[150px] md:max-w-[120px]">
                    <h1 v-else-if="props.companyConfig?.company_name" class="text-2xl md:text-xl text-gray-900 dark:text-white">{{ props.companyConfig.company_name }}</h1>
                </Link>
            </div>
            <LangToggle :languages="languages" :currentLanguage="currentLanguage" class="text-gray-600 dark:text-gray-300" />

            <div class="flex items-center justify-center mt-0 flex-1">
                <div class="w-full max-w-[480px]">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ $t('Set new password') }}</h1>
                    <p class="text-base text-gray-600 dark:text-gray-400 mb-8">{{ $t('Choose a strong password for your account.') }}</p>

                    <div v-if="props.flash?.status?.message"
                         class="mb-6 p-4 rounded-xl bg-green-50 dark:bg-green-900/30 border border-green-100 dark:border-green-700">
                        <p class="text-sm text-green-700 dark:text-green-200">{{ props.flash.status.message }}</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('New Password') }}</label>
                            <FormInput
                                v-model="form.password"
                                type="password"
                                :error="form.errors.password"
                                :placeholder="$t('Min. 8 characters')"
                                class="block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 transition-colors"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('Confirm Password') }}</label>
                            <FormInput
                                v-model="form.password_confirmation"
                                type="password"
                                :error="form.errors.password_confirmation"
                                :placeholder="$t('Repeat your password')"
                                class="block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 border-gray-200 dark:border-gray-700 transition-colors"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="relative w-full inline-flex items-center justify-center px-4 py-3.5 bg-primary text-white rounded-xl hover:bg-secondary transition-all duration-200 font-medium shadow-sm hover:shadow-lg disabled:opacity-70">
                            <svg v-if="form.processing" class="animate-spin -ms-1 me-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ form.processing ? $t('Saving...') : $t('Reset password') }}
                        </button>
                    </form>

                    <div class="text-center pt-8">
                        <Link href="/login" class="text-sm font-normal underline text-primary hover:text-secondary transition-colors">
                            {{ $t('Back to login') }}
                        </Link>
                    </div>
                </div>
            </div>

            <div class="flex-1"></div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FormInput from '@/Components/FormInput.vue';
import LangToggle from '@/Components/LangToggle.vue';
import { computed } from 'vue';

const props = defineProps(['flash', 'companyConfig']);
const page = usePage();
const languages = computed(() => page.props.languages);
const currentLanguage = computed(() => page.props.currentLanguage);

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', { preserveScroll: true });
};
</script>
