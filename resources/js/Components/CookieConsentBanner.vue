<template>
    <transition name="slide-up">
        <div v-if="showBanner" class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-gray-200 shadow-2xl">
            <div class="max-w-7xl mx-auto px-5 md:px-10 lg:px-20 py-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm md:text-base text-gray-700 leading-relaxed">
                            {{ $t('We use cookies to enhance your browsing experience, analyze site traffic, and personalize content. By clicking "Accept All", you consent to our use of cookies.') }}
                            <Link href="/cookie-policy" class="text-primary hover:text-secondary underline font-medium ms-1">
                                {{ $t('Learn more') }}
                            </Link>
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3 flex-shrink-0">
                        <button type="button"
                            @click="acceptAll"
                            class="px-6 py-2.5 bg-primary hover:bg-secondary text-white rounded-lg text-sm font-medium transition-colors whitespace-nowrap"
                        >
                            {{ $t('Accept All') }}
                        </button>
                        <button type="button"
                            @click="acceptEssential"
                            class="px-6 py-2.5 border border-gray-300 hover:border-primary text-gray-700 hover:text-primary rounded-lg text-sm font-medium transition-colors bg-white whitespace-nowrap"
                        >
                            {{ $t('Essential Only') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
    import { ref, onMounted } from 'vue';
    import { Link } from '@inertiajs/vue3';

    const showBanner = ref(false);
    const COOKIE_CONSENT_KEY = 'cookie_consent';
    const COOKIE_CONSENT_EXPIRY_DAYS = 365;

    const checkConsent = () => {
        const consent = localStorage.getItem(COOKIE_CONSENT_KEY);
        if (!consent) {
            showBanner.value = true;
        }
    };

    const setCookieConsent = (value) => {
        const expiryDate = new Date();
        expiryDate.setTime(expiryDate.getTime() + (COOKIE_CONSENT_EXPIRY_DAYS * 24 * 60 * 60 * 1000));
        
        const consentData = {
            value: value,
            timestamp: new Date().toISOString(),
            expiry: expiryDate.toISOString()
        };
        
        localStorage.setItem(COOKIE_CONSENT_KEY, JSON.stringify(consentData));
        showBanner.value = false;
    };

    const acceptAll = () => {
        setCookieConsent('all');
    };

    const acceptEssential = () => {
        setCookieConsent('essential');
    };

    onMounted(() => {
        checkConsent();
    });
</script>

<style scoped>
.slide-up-enter-active {
    transition: all 0.3s ease-out;
}

.slide-up-leave-active {
    transition: all 0.2s ease-in;
}

.slide-up-enter-from {
    transform: translateY(100%);
    opacity: 0;
}

.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}

.slide-up-enter-to,
.slide-up-leave-from {
    transform: translateY(0);
    opacity: 1;
}
</style>

