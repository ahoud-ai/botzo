<template>
    <transition name="slide-up">
        <div v-if="showBanner" class="fixed bottom-0 left-0 right-0 z-50 border-t-[0.5px] border-[#5a6b7e] bg-white shadow-[0_-8px_30px_rgba(10,15,23,0.08)] dark:border-[#94a3b8] dark:bg-[#0a0f17] dark:shadow-[0_-8px_30px_rgba(0,0,0,0.35)]">
            <div class="max-w-7xl mx-auto px-5 md:px-10 lg:px-20 py-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                    <div class="flex-1">
                        <p dir="auto" class="text-sm leading-relaxed text-[#8899aa] md:text-base">
                            {{ $t('We use cookies to enhance your browsing experience, analyze site traffic, and personalize content. By clicking "Accept All", you consent to our use of cookies.') }}
                            <Link href="/cookie-policy" class="ms-1 font-medium text-[#25d366] underline hover:text-[#1db954]">
                                {{ $t('Learn more') }}
                            </Link>
                        </p>
                    </div>
                    <div class="flex flex-shrink-0 flex-col gap-3 sm:flex-row">
                        <button type="button"
                            @click="acceptAll"
                            class="whitespace-nowrap rounded-2xl px-6 py-2.5 text-sm font-semibold text-[#04130a] transition-transform duration-200 hover:scale-[1.03]"
                            style="background-image: linear-gradient(135.79deg, #25d366 0%, #1db954 100%)"
                        >
                            {{ $t('Accept All') }}
                        </button>
                        <button type="button"
                            @click="acceptEssential"
                            class="whitespace-nowrap rounded-2xl border border-[#5a6b7e] bg-transparent px-6 py-2.5 text-sm font-medium text-black transition-colors hover:border-[#25d366] hover:text-[#25d366] dark:border-[#94a3b8] dark:text-white"
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

