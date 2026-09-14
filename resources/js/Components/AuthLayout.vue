<template>
    <div class="min-h-[100svh] flex bg-white dark:bg-[#0a0f17] font-ibm-plex-arabic" dir="ltr">
        <div :dir="isRtl ? 'rtl' : 'ltr'" class="relative lg:w-1/2 flex-1 flex flex-col min-h-[100svh] mx-4 sm:mx-8 md:mx-10 lg:mx-12 xl:mx-16">
            <!-- Top section (floating, doesn't affect vertical centering of content below) -->
            <div class="absolute top-8 md:top-10 inset-x-0 flex justify-end items-center z-10">
                <div class="flex items-center gap-2">
                    <ThemeToggle />
                    <LangToggle :languages="languages" :currentLanguage="currentLanguage" class="text-gray-600 dark:text-gray-300" />
                </div>
            </div>

            <!-- Content section -->
            <div class="flex items-center justify-center flex-1 pt-20 pb-6 lg:pt-0 lg:pb-0">
                <div class="auth-content-enter w-full max-w-[480px] flex flex-col gap-8">
                    <!-- Mobile logo (desktop uses the side-panel logo instead) -->
                    <div class="lg:hidden w-full flex justify-center">
                        <!-- Dark theme mark -->
                        <div class="relative hidden dark:block" style="width: 76.721px; height: 70.003px;">
                            <img src="/images/auth/mobile-icon-dark.svg" alt="" class="absolute max-w-none" style="left: 23.82%; top: 0; width: 52.48%; height: 56.09%;">
                            <img src="/images/auth/mobile-wordmark-dark.svg" alt="Botzo" class="absolute max-w-none" style="left: 0; top: 58.93%; width: 100%; height: 27.78%;">
                            <p class="absolute whitespace-nowrap" style="left: 5.6%; top: 91.55%; font-size: 4.84px; letter-spacing: 0.1936px; font-family: Inter, sans-serif; line-height: 0;">
                                <span style="color: #9eed42;">WhatsApp</span><span class="text-white"> &amp; </span><span style="color: #4230f8;">Meta</span><span class="text-white"> Solutions</span>
                            </p>
                        </div>
                        <!-- Light theme mark -->
                        <div class="relative block dark:hidden" style="width: 69.046px; height: 63px;">
                            <img src="/images/auth/mobile-icon-light.svg" alt="" class="absolute max-w-none" style="left: 23.82%; top: 0; width: 52.47%; height: 56.09%;">
                            <img src="/images/auth/mobile-wordmark-light.svg" alt="Botzo" class="absolute max-w-none" style="left: 0; top: 58.93%; width: 100%; height: 27.78%;">
                            <p class="absolute whitespace-nowrap text-[#25d366]" style="left: 5.6%; top: 91.56%; font-size: 4.353px; letter-spacing: 0.1741px; font-family: Inter, sans-serif; line-height: 0;">
                                WhatsApp &amp; Meta Solutions
                            </p>
                        </div>
                    </div>

                    <slot />
                </div>
            </div>
        </div>

        <!-- Desktop side panel -->
        <div
            class="hidden lg:flex lg:w-1/2 relative flex-col items-center justify-center gap-8 p-10 overflow-hidden"
            style="background: linear-gradient(95.5deg, #25D366 0%, #3B82F6 100%);"
        >
            <div class="absolute inset-0 pointer-events-none opacity-80" aria-hidden="true">
                <img src="/images/auth/login-decor-1.svg" alt="" class="absolute max-w-none auth-wave auth-wave--1" style="left: 0%; top: 55.41%; width: 96.45%; height: 49.68%;">
                <img src="/images/auth/login-decor-3.svg" alt="" class="absolute max-w-none auth-wave auth-wave--2" style="left: 1.1%; top: -26.68%; width: 96.47%; height: 49.67%;">
                <img src="/images/auth/login-decor-4.svg" alt="" class="absolute max-w-none auth-wave auth-wave--3" style="left: 30.4%; top: -16.22%; width: 76.5%; height: 51.4%;">
            </div>
            <div class="relative flex flex-col items-center">
                <img src="/images/auth/botzo-auth-mark.svg" alt="Botzo" class="w-40 xl:w-44" />
            </div>
        </div>
    </div>
</template>

<script setup>
    import { usePage } from '@inertiajs/vue3';
    import LangToggle from '@/Components/LangToggle.vue';
    import ThemeToggle from '@/Components/ThemeToggle.vue';
    import { useRtl } from '@/Composables/useRtl';
    import { computed } from 'vue';

    const page = usePage();
    const { isRtl } = useRtl();
    const languages = computed(() => page.props.languages);
    const currentLanguage = computed(() => page.props.currentLanguage);
</script>
