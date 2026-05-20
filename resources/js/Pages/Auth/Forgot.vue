<template>
    <Head>
        <title>{{ $t('Forgot Password') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
        <meta name="description" content="Reset your password. We'll send you instructions via email." />
    </Head>
    <div class="min-h-[100svh] bg-white dark:bg-gray-900">
        <div class="flex flex-col justify-between min-h-[100svh] mx-4 sm:mx-8 md:mx-10">
            <!-- Top section with logo -->
            <div class="flex justify-start md:justify-between items-center mt-8 md:mt-10 mb-4">
                <!-- Logo -->
                <div class="flex items-center">
                    <Link href="/" class="inline-block">
                        <img v-if="props.companyConfig && props.companyConfig.logo" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name" class="max-w-[150px] md:max-w-[120px]">
                        <h1 v-else-if="props.companyConfig?.company_name" class="text-2xl md:text-xl text-gray-900 dark:text-white">{{ props.companyConfig.company_name }}</h1>
                    </Link>
                </div>
            </div>

            <!-- Content section -->
            <div class="flex items-center justify-center mt-0 flex-1">
                <div class="w-full max-w-[480px]">
                    <!-- Welcome text -->
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ $t('Reset Password') }}</h1>
                    <p class="text-base md:text-lg text-gray-600 dark:text-gray-400 mb-8">
                        {{ $t('We\'ll send you instructions via email.') }}
                    </p>

                    <!-- Success message -->
                    <div v-if="props.flash?.status?.message" 
                         class="mb-6 p-4 rounded-xl bg-green-50 dark:bg-green-900/30 border border-green-100 dark:border-green-700">
                        <p class="text-sm text-green-700 dark:text-green-200">
                            {{ props.flash?.status?.message }}
                        </p>
                    </div>

                    <!-- Form wrapper -->
                    <form @submit.prevent="submitForm()" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ $t('Email') }}
                            </label>
                            <FormInput 
                                v-model="form.email" 
                                type="email"
                                :error="form.errors.email" 
                                :placeholder="$t('Enter your email address')"
                                :class="[
                                    'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                    form.errors.email 
                                        ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                        : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                ]"
                            />
                        </div>

                            <button
                                type="submit"
                                :disabled="isLoading"
                                class="relative w-full inline-flex items-center justify-center px-4 py-3.5 bg-primary text-white rounded-xl hover:bg-secondary transition-all duration-200 font-medium shadow-sm hover:shadow-lg disabled:opacity-70">
                            <span class="flex items-center">
                                <svg v-if="isLoading" 
                                     class="animate-spin -ms-1 me-2 h-4 w-4 text-white" 
                                     xmlns="http://www.w3.org/2000/svg" 
                                     fill="none" 
                                     viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isLoading ? $t('Sending...') : $t('Send password reset link') }}
                            </span>
                        </button>
                    </form>

                    <!-- Login link -->
                    <div class="text-center pt-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('Remembered password?') }}</span>
                        <Link href="/login" class="text-sm font-normal underline text-primary hover:text-secondary transition-colors ms-1">
                            {{ $t('Login here') }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Spacer -->
            <div class="flex-1"></div>
        </div>
    </div>
</template>

<script setup>
    import { Head, Link, useForm } from "@inertiajs/vue3";
    import FormInput from '@/Components/FormInput.vue';
    import { defineProps, ref } from 'vue';

    const props = defineProps(['flash', 'config', 'companyConfig']);

    const isLoading = ref(false);

    const form = useForm({
        email: null
    })
    const submitForm = async () => {
        isLoading.value = true;
        form.post('forgot-password', {
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            }
        });
    };
</script>
