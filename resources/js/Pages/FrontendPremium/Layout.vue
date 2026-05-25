<template>
    {{ 'test' }}
    <div :class="rtlClass">
        <div class="flex items-center bg-secondary/70 h-8 px-5 md:px-10 lg:px-20 2xl:px-60">
            <div class="text-sm text-white">
                <div class="truncate w-[20em] md:w-full flex items-center gap-x-1">
                    <span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 48 48"><path fill="currentColor" d="M44 24c0 11.046-8.954 20-20 20S4 35.046 4 24S12.954 4 24 4s20 8.954 20 20m-20-4c-.69 0-1.25.56-1.25 1.25v12.5a1.25 1.25 0 1 0 2.5 0v-12.5c0-.69-.56-1.25-1.25-1.25m0-3a2 2 0 1 0 0-4a2 2 0 0 0 0 4"/></svg>
                    </span>
                    {{ $t('Header.message') }}
                </div>
            </div>
            <div :class="[isRtl ? 'me-auto' : 'ms-auto']">
                <LangToggle class="text-white" :languages="props.languages" :currentLanguage="props.currentLanguage" />
            </div>
        </div>
        <section id="section1" class="pt-4 md:pt-10 px-5 md:px-10 lg:px-20 2xl:px-60 relative">
            <div class="flex justify-between items-center">
                <div>
                    <div class="premium-brand-lockup" v-if="props.companyConfig.logo">
                        <img class="premium-brand-lockup__mark" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                        <span class="premium-brand-lockup__text">
                            <span class="premium-brand-lockup__name"><span>botz</span><span class="premium-brand-lockup__accent">o</span></span>
                        </span>
                    </div>
                    <h1 v-else class="text-2xl">{{ props.companyConfig.company_name }}</h1>
                </div>
                <div class="hidden lg:flex justify-center items-center text-md gap-x-8">
                    <a href="#section2" class="cursor-pointer hover:border-b-2 border-black">{{ $t('Features') }}</a>
                    <a href="#section4" class="cursor-pointer hover:border-b-2 border-black">{{ $t('Pricing') }}</a>
                    <a href="#section5" class="cursor-pointer hover:border-b-2 border-black">{{ $t('Reviews') }}</a>
                    <a href="#section6" class="cursor-pointer hover:border-b-2 border-black">{{ $t('FAQs') }}</a>
                    <a href="#section7" class="cursor-pointer hover:border-b-2 border-black">{{ $t('Contact us') }}</a>
                </div>
                
                <div class="flex justify-end items-center text-md">
                    <Link href="/login" class="hidden md:inline-block me-10 cursor-pointer hover:text-gray-400 border-black">{{ $t('Login') }}</Link>
                    <Link href="/signup" class="hidden md:inline-block bg-primary hover:bg-secondary text-white p-2 rounded-lg text-sm w-fit px-8">{{ $t('Sign Up') }}</Link>
                    <Link href="/login" class="md:hidden bg-primary hover:bg-secondary text-white p-2 rounded-lg text-sm flex w-fit px-8">{{ $t('Login') }}</Link>
                </div>
            </div>
        </section>

        <slot />

        <footer id="section7" class="bg-gray-300/10 px-5 md:px-10 lg:px-20 md:pt-10 pb-5 2xl:px-60">
            <div class="md:flex">
                <div class="md:w-1/4 lg:w-1/2">
                    <div>
                        <div class="premium-brand-lockup premium-brand-lockup--footer" v-if="props.companyConfig.logo">
                            <img class="premium-brand-lockup__mark" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                            <span class="premium-brand-lockup__text">
                                <span class="premium-brand-lockup__name"><span>botz</span><span class="premium-brand-lockup__accent">o</span></span>
                            </span>
                        </div>
                        <h4 v-else class="text-2xl mb-2">{{ props.companyConfig.company_name }}</h4>
                    </div>
                </div>
                <div class="md:w-3/4 lg:w-1/2 md:flex md:justify-between text-sm space-y-8 md:space-y-0">
                    <div>
                        <h4 class="text-xl font-regular mb-2">{{ $t('Links') }}</h4>
                        <div class="flex md:justify-between gap-x-10 md:gap-x-10 lg:gap-x-20 space-y-2 md:space-y-0">
                            <div class="space-y-2">
                                <div>
                                    <a href="#section2" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Features') }}</a>
                                </div>
                                <div>
                                    <a href="#section3" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('How it works') }}</a>
                                </div>
                                <div>
                                    <a href="#section4" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Pricing') }}</a>
                                </div>
                                <div>
                                    <a href="#section5" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Reviews') }}</a>
                                </div>
                                <div>
                                    <a href="#section6" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('FAQs') }}</a>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div>
                                    <Link href="/signup" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Sign up') }}</Link>
                                </div>
                                <div>
                                    <Link href="/login" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Login') }}</Link>
                                </div>
                                <div>
                                    <Link href="/privacy" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Privacy Policy') }}</Link>
                                </div>
                                <div>
                                    <Link href="/tos" class="border-b-2 border-transparent hover:border-b-2 hover:border-black">{{ $t('Terms of Service') }}</Link>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div>
                        <div>
                            <h4 class="text-xl font-regular mb-2">{{ $t('Contact us') }}</h4>
                            <p class="mb-2">{{ props.companyConfig.address }}</p>
                            <p class="mb-2">{{ $t('Email') }}: <a :href="'mailto:' + props.companyConfig.email" class="hover:underline">{{ props.companyConfig.email }}</a></p>
                            <p class="mb-4">{{ $t('Phone') }}: <a :href="'tel:' + props.companyConfig.phone" class="hover:underline">{{ props.companyConfig.phone }}</a></p>
                            <div class="flex gap-x-4">
                                <a v-if="facebookUrl" :href="facebookUrl" class="cursor-pointer">
                                    <svg class="hover:text-secondary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95"/></svg>
                                </a>
                                <a v-if="twitterUrl" :href="twitterUrl" class="cursor-pointer">
                                    <svg class="hover:text-secondary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M22 5.8a8.49 8.49 0 0 1-2.36.64a4.13 4.13 0 0 0 1.81-2.27a8.21 8.21 0 0 1-2.61 1a4.1 4.1 0 0 0-7 3.74a11.64 11.64 0 0 1-8.45-4.29a4.16 4.16 0 0 0-.55 2.07a4.09 4.09 0 0 0 1.82 3.41a4.05 4.05 0 0 1-1.86-.51v.05a4.1 4.1 0 0 0 3.3 4a3.93 3.93 0 0 1-1.1.17a4.9 4.9 0 0 1-.77-.07a4.11 4.11 0 0 0 3.83 2.84A8.22 8.22 0 0 1 3 18.34a7.93 7.93 0 0 1-1-.06a11.57 11.57 0 0 0 6.29 1.85A11.59 11.59 0 0 0 20 8.45v-.53a8.43 8.43 0 0 0 2-2.12"/></svg>
                                </a>
                                <a v-if="tiktokUrl" :href="tiktokUrl" class="cursor-pointer">
                                    <svg class="hover:text-secondary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.4v13.57a2.89 2.89 0 0 1-2.89 2.88a2.89 2.89 0 0 1 0-5.77c.18 0 .35.02.52.05V9.27a6.29 6.29 0 0 0-.52-.02a6.3 6.3 0 0 0 0 12.6a6.3 6.3 0 0 0 6.3-6.3V8.66a8.18 8.18 0 0 0 4.77 1.53V6.82a4.87 4.87 0 0 1-1.01-.13"/></svg>
                                </a>
                                <a v-if="snapchatUrl" :href="snapchatUrl" class="cursor-pointer">
                                    <svg class="hover:text-secondary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7v11l2-2l2 2l3-3l3 3l2-2l2 2V9a7 7 0 0 0-7-7m-3 8a1 1 0 1 1 .01 0zm6 0a1 1 0 1 1 .01 0z"/></svg>
                                </a>
                                <a v-if="linkedinUrl" :href="linkedinUrl" class="cursor-pointer">
                                    <svg class="hover:text-secondary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16"><path fill="currentColor" d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248c-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586c.173-.431.568-.878 1.232-.878c.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252c-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg>
                                </a>
                                <a v-if="slackUrl" :href="slackUrl" class="cursor-pointer">
                                    <svg class="hover:text-secondary" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"><path fill="currentColor" d="M2 14.67a2 2 0 1 0 4 0v-2H4a2 2 0 0 0-2 2m12.64-3.34a2 2 0 0 0 2-2V4a2 2 0 1 0-4 0v5.33a2 2 0 0 0 2.02 2Zm7.32-2a2 2 0 1 0-4 0v2h2a2 2 0 0 0 2.04-2ZM9.34 12.67a2 2 0 0 0-2 2V20a2 2 0 1 0 4 0v-5.33a2 2 0 0 0-2-2M14.66 18h-2v2a2 2 0 1 0 2-2M20 12.67h-5.34a2 2 0 0 0 0 4H20a2 2 0 0 0 0-4M9.34 7.33H4a2 2 0 1 0 0 4h5.34a2 2 0 0 0 0-4m0-5.33a2 2 0 0 0 0 4h2V4a2 2 0 0 0-2-2"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-10 border-t pt-5 text-sm">
                <p class="font-light">{{ $t('Copyright') }} {{ currentYear }}. {{ $t('All rights reserved') }}.</p>
            </div>
        </footer>
    </div>
</template>
<script setup>
    import { ref, onMounted, onBeforeUnmount } from 'vue';
    import { Link } from "@inertiajs/vue3";
    import LangToggle from '@/Components/LangToggle.vue';
    import { useRtl } from '@/Composables/useRtl'

    const config = computed(() => usePage().props.config);

    const currentYear = new Date().getFullYear();
    const facebookUrl = ref(null);
    const twitterUrl = ref(null);
    const tiktokUrl = ref(null);
    const snapchatUrl = ref(null);
    const slackUrl = ref(null);
    const linkedinUrl = ref(null);

    const { rtlClass, isRtl } = useRtl()

    const parseSocials = () => {
        try {
            const socialsArray = JSON.parse(props.companyConfig.socials);

            facebookUrl.value = socialsArray['facebook'] || null;
            twitterUrl.value = socialsArray['twitter'] || null;
            tiktokUrl.value = socialsArray['tiktok'] || null;
            snapchatUrl.value = socialsArray['snapchat'] || null;
            slackUrl.value = socialsArray['slack'] || null;
            linkedinUrl.value = socialsArray['linkedin'] || null;
        } catch (error) {
            console.error('Error parsing socials:', error);
        }
    };

    const getDetail = (value, key) => {
        if(value){
            const item = JSON.parse(value);
            return item?.[key] ?? null;
        } else {
            return null;
        }
    }

    const selectedFeature = ref(1);
    const selectedFaq = ref(1);

    onMounted(() => {
        parseSocials();
    });
</script>
