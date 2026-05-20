<template>
    <Head>
        <title>{{ $t('Sign Up') }} - {{ props.companyConfig?.company_name || 'Botzo' }}</title>
        <meta name="description" content="Create your account to start transforming your business communication." />
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
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white mb-2">{{ $t('Get started now!') }}</h1>
                    <p class="text-base md:text-lg text-gray-600 dark:text-gray-400 mb-8">
                        {{ $t('Continue with Google or get started with your email.') }}
                    </p>

                    <!-- Form wrapper -->
                    <form @submit.prevent="submitForm()" class="space-y-6">
                        <!-- Social login buttons -->
                        <div v-if="props.companyConfig?.allow_google_login === '1' || props.companyConfig?.allow_facebook_login === '1'" class="space-y-3">
                            <button v-if="props.companyConfig?.allow_google_login === '1'" type="button" @click="handleGoogleLogin"
                                class="mb-6 flex w-full items-center justify-center gap-3 px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all duration-200">
                                <svg class="h-5 w-5" viewBox="0 0 24 24">
                                    <path d="M23.766 12.277c0-.844-.068-1.686-.219-2.507H12.24v4.77h6.48c-.27 1.509-1.125 2.783-2.394 3.636v3.006h3.867c2.261-2.087 3.573-5.169 3.573-8.905z" fill="#4285F4"/>
                                    <path d="M12.24 24c3.236 0 5.966-1.062 7.954-2.818l-3.867-3.006c-1.073.732-2.456 1.146-4.087 1.146-3.13 0-5.79-2.115-6.745-4.958h-4.001v3.101C3.483 21.123 7.561 24 12.24 24z" fill="#34A853"/>
                                    <path d="M5.495 14.364c-.244-.723-.382-1.496-.382-2.364s.138-1.641.382-2.364V6.535H1.494C.544 8.195 0 10.089 0 12s.544 3.805 1.494 5.465l4.001-3.101z" fill="#FBBC05"/>
                                    <path d="M12.24 4.678c1.765 0 3.348.604 4.597 1.794l3.42-3.42C18.203 1.14 15.473 0 12.24 0 7.561 0 3.483 2.877 1.494 6.535l4.001 3.101c.954-2.843 3.615-4.958 6.745-4.958z" fill="#EA4335"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $t('Continue with Google') }}
                                </span>
                            </button>

                            <button v-if="props.companyConfig?.allow_facebook_login === '1'" type="button" @click="handleFacebookLogin"
                                class="mb-6 flex w-full items-center justify-center gap-3 px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-all duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 256 256">
                                    <path fill="#1877F2" d="M256 128C256 57.308 198.692 0 128 0C57.308 0 0 57.307 0 128c0 63.888 46.808 116.843 108 126.445V165H75.5v-37H108V99.8c0-32.08 19.11-49.8 48.347-49.8C170.352 50 185 52.5 185 52.5V84h-16.14C152.958 84 148 93.867 148 103.99V128h35.5l-5.675 37H148v89.445c61.192-9.602 108-62.556 108-126.445"/>
                                    <path fill="#FFF" d="m177.825 165l5.675-37H148v-24.01C148 93.866 152.959 84 168.86 84H185V52.5S170.352 50 156.347 50C127.11 50 108 67.72 108 99.8V128H75.5v37H108v89.445A128.959 128.959 0 0 0 128 256a128.9 128.9 0 0 0 20-1.555V165h29.825"/>
                                </svg>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $t('Continue with Facebook') }}
                                </span>
                            </button>

                            <div v-if="props.companyConfig?.allow_google_login === '1' || props.companyConfig?.allow_facebook_login === '1'" class="relative">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
                                </div>
                                <div class="relative flex justify-center text-sm">
                                    <span class="px-4 bg-white dark:bg-gray-900 text-gray-500 dark:text-gray-400">
                                        {{ $t('or continue with email') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Registration form fields -->
                        <div class="space-y-4">
                            <div class="grid gap-x-6 gap-y-4 grid-cols-6">
                                <div class="col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ $t('First name') }}
                                    </label>
                                    <FormInput 
                                        v-model="form.first_name" 
                                        type="text"
                                        :error="form.errors.first_name" 
                                        :placeholder="$t('First name')"
                                        :class="[
                                            'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                            form.errors.first_name 
                                                ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                                : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                        ]"/>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ $t('Last name') }}
                                    </label>
                                    <FormInput 
                                        v-model="form.last_name" 
                                        type="text"
                                        :error="form.errors.last_name" 
                                        :placeholder="$t('Last name')"
                                        :class="[
                                            'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                            form.errors.last_name 
                                                ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                                : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                        ]"/>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $t('Organization name') }}
                                </label>
                                <FormInput 
                                    v-model="form.organization_name" 
                                    type="text"
                                    :error="form.errors.organization_name" 
                                    :placeholder="$t('Organization name')"
                                    :class="[
                                        'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                        form.errors.organization_name 
                                            ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                            : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                    ]"/>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $t('Email') }}
                                </label>
                                <FormInput 
                                    v-model="form.email" 
                                    type="email"
                                    :error="form.errors.email" 
                                    :placeholder="$t('Enter your email')"
                                    :class="[
                                        'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                        form.errors.email 
                                            ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                            : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                    ]"/>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $t('Phone') }}
                                </label>
                                <FormPhoneInput 
                                    v-model="form.phone" 
                                    :allowed-countries="props.signupPhoneCountries"
                                    :error="form.errors.phone" 
                                    :class="[
                                        'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                        form.errors.phone 
                                            ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                            : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                    ]"/>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $t('Password') }}
                                </label>
                                <FormInput 
                                    v-model="form.password" 
                                    type="password"
                                    :error="form.errors.password" 
                                    :placeholder="$t('Create a password')"
                                    :class="[
                                        'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                        form.errors.password 
                                            ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                            : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                    ]"/>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    {{ $t('Confirm password') }}
                                </label>
                                <FormInput 
                                    v-model="form.password_confirmation" 
                                    type="password"
                                    :error="form.errors.password_confirmation" 
                                    :placeholder="$t('Confirm your password')"
                                    :class="[
                                        'block w-full h-12 rounded-xl bg-gray-50 dark:bg-gray-900 transition-colors',
                                        form.errors.password_confirmation 
                                            ? 'border-red-500 dark:border-red-500 focus:ring-red-500 focus:border-red-500' 
                                            : 'border-gray-200 dark:border-gray-700 focus:ring-primary focus:border-primary'
                                    ]"/>
                            </div>
                        </div>

                            <button type="submit"
                                :disabled="isLoading"
                                class="relative w-full inline-flex items-center justify-center px-4 py-3.5 bg-primary text-white rounded-xl hover:bg-secondary transition-all duration-200 font-medium shadow-sm hover:shadow-lg disabled:opacity-70">
                            <span class="flex items-center">
                                <svg v-if="isLoading" class="animate-spin -ms-1 me-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ isLoading ? $t('Creating account...') : $t('Get Started') }}
                            </span>
                        </button>
                    </form>

                    <!-- Terms and Privacy Policy -->
                    <div class="pt-6">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $t('By registering, you agree to our') }} 
                            <Link href="/terms-of-service" target="_blank" rel="noopener noreferrer" class="text-primary hover:text-secondary underline transition-colors">{{ $t('terms of service') }}</Link> 
                            {{ $t('and') }} 
                            <Link href="/privacy" target="_blank" rel="noopener noreferrer" class="text-primary hover:text-secondary underline transition-colors">{{ $t('privacy policy') }}</Link>.
                        </p>
                    </div>

                    <!-- Sign in link -->
                    <div class="text-center pt-8">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('Already have an account?') }}</span>
                        <Link href="/login" class="text-sm font-normal underline text-primary hover:text-secondary transition-colors ms-1">
                            {{ $t('Sign in') }}
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
    import FormPhoneInput from '@/Components/FormPhoneInput.vue';
    import { defineProps, ref } from 'vue';

    const props = defineProps(['flash', 'config', 'companyConfig', 'signupPhoneCountries']);

    const isLoading = ref(false);

    const form = useForm({
        first_name: null,
        last_name: null,
        organization_name: null,
        email: null,
        phone: null,
        password: null,
        password_confirmation: null
    });
    const submitForm = async () => {
        isLoading.value = true;
        form.post('signup', {
            preserveScroll: true,
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
</script>
