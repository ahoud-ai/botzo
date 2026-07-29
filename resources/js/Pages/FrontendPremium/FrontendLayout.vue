<template>
    <div :class="[rtlClass, 'font-ibm-plex-arabic', 'page-enter-anim']">
        <!-- Sticky Header -->
        <header
            :class="[
                'sticky top-0 ui-layer-content border-b transition-[background-color,border-color,box-shadow] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]',
                isScrolled
                    ? 'bg-white/90 dark:bg-[#0a0f17]/90 backdrop-blur-md border-[#cfd8e3] dark:border-white/10 shadow-[0_8px_24px_-16px_rgba(10,15,23,0.25)]'
                    : 'bg-white dark:bg-[#0a0f17] border-transparent shadow-none',
            ]"
        >
            <div
                class="px-4 md:px-10 lg:px-20 transition-[padding] duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                :class="isScrolled ? 'py-3 lg:py-4' : 'py-6 lg:py-8'"
            >
                <div class="flex justify-between items-center">
                    <!-- Group 1: logo -->
                    <Link
                        href="/"
                        class="premium-brand-lockup shrink-0 origin-center transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]"
                        :class="isScrolled ? 'scale-[0.92]' : 'scale-100'"
                    >
                        <template v-if="props.companyConfig && props.companyConfig.logo && props.companyConfig.company_name">
                            <img class="premium-brand-lockup__mark" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                            <span class="premium-brand-lockup__text">
                                <span class="premium-brand-lockup__name"><span>botz</span><span class="premium-brand-lockup__accent">o</span></span>
                            </span>
                        </template>
                        <template v-else>
                            <NavBrandMark class="hidden lg:block" variant="desktop" />
                            <NavBrandMark class="lg:hidden" variant="mobile" />
                        </template>
                    </Link>

                    <!-- Group 2: desktop nav links -->
                    <nav class="hidden lg:flex items-center gap-8">
                        <Link href="/#section2" class="text-base leading-6 text-black dark:text-white hover:opacity-70 transition-opacity">{{ $t('Features') }}</Link>
                        <Link href="/#section3" class="text-base leading-6 text-black dark:text-white hover:opacity-70 transition-opacity">{{ $t('How it works') }}</Link>
                        <Link href="/pricing" class="text-base leading-6 text-black dark:text-white hover:opacity-70 transition-opacity" :class="page.url === '/pricing' ? 'font-semibold' : ''">{{ $t('Pricing') }}</Link>
                        <Link href="/faqs" class="text-base leading-6 text-black dark:text-white hover:opacity-70 transition-opacity" :class="page.url === '/faqs' ? 'font-semibold' : ''">{{ $t('FAQs') }}</Link>
                        <Link href="/contact" class="text-base leading-6 text-black dark:text-white hover:opacity-70 transition-opacity" :class="page.url === '/contact' ? 'font-semibold' : ''">{{ $t('Contact Us') }}</Link>
                        <div class="relative" @mouseenter="showResourcesDropdown = true" @mouseleave="showResourcesDropdown = false">
                            <button type="button" class="flex items-center gap-1 text-base leading-6 text-black dark:text-white hover:opacity-70 transition-opacity">
                                {{ $t('More') }}
                                <svg class="w-4 h-4 transition-transform duration-200" :class="showResourcesDropdown ? 'rotate-180' : ''" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M12.5 6L8 10.5L3.5 6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </button>
                            <transition name="dropdown">
                                <div v-if="showResourcesDropdown" class="absolute top-full mt-2 bg-white dark:bg-[#0a0f17] border border-[#cfd8e3] dark:border-white/10 rounded-2xl shadow-2xl py-6 px-6 w-[600px] max-w-[90vw] overflow-hidden ui-dropdown-layer ui-dropdown-start">
                                    <div class="grid grid-cols-2 gap-8">
                                        <!-- API docs Column -->
                                        <div>
                                            <h3 class="text-base leading-5 font-semibold text-gray-500 dark:text-gray-400 mb-4">{{ $t('Help & Support') }}</h3>
                                            <Link href="/api-documentation" class="group block p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-all duration-150">
                                                <div class="text-base leading-6 font-normal text-black dark:text-white">{{ $t('API Documentation') }}</div>
                                                <div class="text-base leading-6 font-normal text-gray-500 dark:text-gray-400 mt-0.5">{{ $t('Integrate with our REST API') }}</div>
                                            </Link>
                                        </div>

                                        <!-- Pages Column -->
                                        <div v-if="props.pages && props.pages.length > 0">
                                            <h3 class="text-base leading-5 font-semibold text-gray-500 dark:text-gray-400 mb-4">{{ $t('Pages') }}</h3>
                                            <div class="space-y-1">
                                                <Link v-for="page in props.pages" :key="page.id" :href="'/pages/' + (page.slug || formattedName(page.name))" class="group block p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-all duration-150">
                                                    <span class="text-base leading-6 font-normal text-black dark:text-white">{{ page.display_name || page.name }}</span>
                                                </Link>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </nav>

                    <!-- Group 3: actions (theme, language, login/dashboard, mobile menu) -->
                    <div class="flex items-center gap-0 lg:gap-3">
                        <ThemeToggle />
                        <LangToggle v-if="page.props.languages && page.props.languages.length > 1" :languages="page.props.languages" :currentLanguage="currentLanguageCode" />

                        <template v-if="!isAuthenticated">
                            <Link href="/login" class="hidden lg:inline-flex h-11 w-[140px] items-center justify-center rounded-xl bg-[#25d366] text-[#04130a] font-semibold text-base leading-5 hover:brightness-95 transition">
                                {{ $t('Login') }}
                            </Link>
                        </template>
                        <template v-else>
                            <Link href="/dashboard" class="hidden lg:inline-flex h-11 items-center justify-center rounded-xl bg-[#25d366] text-[#04130a] font-semibold text-base leading-5 px-8 hover:brightness-95 transition">
                                {{ $t('Go to Dashboard') }}
                            </Link>
                        </template>

                        <!-- Mobile Menu Button -->
                        <button type="button" @click="showMobileMenu = !showMobileMenu" class="lg:hidden inline-flex items-center justify-center w-[18px] h-[18px] text-black dark:text-white shrink-0">
                            <svg v-if="!showMobileMenu" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                <path d="M1 2.25H17M1 9H17M1 15.75H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <svg v-else width="18" height="18" viewBox="0 0 18 18" fill="none">
                                <path d="M14 4L4 14M4 4L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Menu Overlay -->
        <transition name="fade">
            <div v-if="showMobileMenu" class="lg:hidden fixed inset-0 ui-layer-drawer">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="showMobileMenu = false"></div>

                <!-- Menu Panel -->
                <div :class="['absolute top-0 right-0 h-full w-80 max-w-[85vw] bg-white dark:bg-[#0a0f17] shadow-xl overflow-y-auto mobile-menu-panel', mobileMenuPanelAnimationClass]">
                    <div class="p-6">
                        <!-- Close Button -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-semibold text-black dark:text-white">{{ $t('Menu') }}</h2>
                            <button type="button" @click="showMobileMenu = false" class="p-2 rounded-lg text-black dark:text-white hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>

                        <!-- Navigation Links -->
                        <nav class="space-y-2">
                            <Link href="/#section2" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium text-black dark:text-white">
                                {{ $t('Features') }}
                            </Link>
                            <Link href="/#section3" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium text-black dark:text-white">
                                {{ $t('How it works') }}
                            </Link>
                            <Link href="/pricing" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium" :class="page.url === '/pricing' ? 'bg-black/5 dark:bg-white/10 text-[#25d366]' : 'text-black dark:text-white'">
                                {{ $t('Pricing') }}
                            </Link>
                            <Link href="/faqs" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium" :class="page.url === '/faqs' ? 'bg-black/5 dark:bg-white/10 text-[#25d366]' : 'text-black dark:text-white'">
                                {{ $t('FAQs') }}
                            </Link>
                            <Link href="/api-documentation" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium" :class="page.url === '/api-documentation' ? 'bg-black/5 dark:bg-white/10 text-[#25d366]' : 'text-black dark:text-white'">
                                {{ $t('API Documentation') }}
                            </Link>

                            <!-- Dynamic Pages -->
                            <Link
                                v-for="pageItem in props.pages"
                                :key="pageItem.id"
                                :href="'/pages/' + (pageItem.slug || formattedName(pageItem.name))"
                                @click="showMobileMenu = false"
                                class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium"
                                :class="page.url === '/pages/' + (pageItem.slug || formattedName(pageItem.name)) ? 'bg-black/5 dark:bg-white/10 text-[#25d366]' : 'text-black dark:text-white'"
                            >
                                {{ pageItem.display_name || pageItem.name }}
                            </Link>

                            <Link href="/contact" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 transition-colors font-medium" :class="page.url === '/contact' ? 'bg-black/5 dark:bg-white/10 text-[#25d366]' : 'text-black dark:text-white'">
                                {{ $t('Contact Us') }}
                            </Link>
                        </nav>

                        <!-- Language Selector (Mobile) -->
                        <div v-if="page.props.languages && page.props.languages.length > 1" class="mt-6 pt-6 border-t border-[#cfd8e3] dark:border-white/10">
                            <div class="relative">
                                <button type="button"
                                    @click="showMobileLanguageDropdown = !showMobileLanguageDropdown"
                                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg border border-[#cfd8e3] dark:border-white/10 hover:bg-black/5 dark:hover:bg-white/5 transition-colors"
                                >
                                    <span class="font-medium text-black dark:text-white">{{ page.props.languages.find(l => l.code === currentLanguageCode)?.name || currentLanguageCode.toUpperCase() }}</span>
                                    <svg class="w-4 h-4 transition-transform duration-200 text-gray-500 dark:text-gray-400" :class="showMobileLanguageDropdown ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                                <transition name="dropdown">
                                    <div v-if="showMobileLanguageDropdown" class="absolute top-full inset-x-0 mt-2 bg-white dark:bg-[#0a0f17] border border-[#cfd8e3] dark:border-white/10 rounded-lg shadow-lg py-2 max-h-60 overflow-y-auto ui-layer-dropdown">
                                        <a
                                            v-for="language in page.props.languages"
                                            :key="language.id"
                                            :href="'/language/' + language.code"
                                            class="block px-4 py-2 text-sm transition-colors"
                                            :class="language.code === currentLanguageCode ? 'bg-black/5 dark:bg-white/10 font-semibold text-[#25d366]' : 'text-black dark:text-white hover:bg-black/5 dark:hover:bg-white/5'"
                                            @click="showMobileLanguageDropdown = false"
                                        >
                                            {{ language.name }}
                                        </a>
                                    </div>
                                </transition>
                            </div>
                        </div>

                        <!-- Action Buttons (Mobile) -->
                        <div class="mt-6 pt-6 border-t border-[#cfd8e3] dark:border-white/10 space-y-3">
                            <template v-if="!isAuthenticated">
                                <Link href="/login" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 border border-[#cfd8e3] dark:border-white/10 text-black dark:text-white hover:bg-black/5 dark:hover:bg-white/5 rounded-xl transition-colors font-medium">
                                    {{ $t('Login') }}
                                </Link>
                                <a v-if="props.companyConfig && props.companyConfig.book_a_demo_link" :href="props.companyConfig.book_a_demo_link" target="_blank" rel="noopener noreferrer" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 border border-[#cfd8e3] dark:border-white/10 text-black dark:text-white hover:bg-black/5 dark:hover:bg-white/5 rounded-xl transition-colors font-medium">
                                    {{ $t('Book a demo') }}
                                </a>
                                <Link href="/signup" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 bg-[#25d366] text-[#04130a] hover:brightness-95 rounded-xl transition font-semibold">
                                    {{ $t('Sign up') }}
                                </Link>
                            </template>
                            <template v-else>
                                <Link href="/dashboard" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 bg-[#25d366] text-[#04130a] hover:brightness-95 rounded-xl transition font-semibold">
                                    {{ $t('Go to Dashboard') }}
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Page Content -->
        <slot />

        <!-- Cookie Consent Banner -->
        <CookieConsentBanner />

        <!-- Footer -->
        <footer id="section7" class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-[72px]">
                <div class="flex w-full flex-col items-center gap-10 lg:flex-row lg:items-center lg:justify-between lg:gap-0" dir="ltr">
                    <!-- More about Botzo -->
                    <div class="flex w-full flex-col items-end gap-[18px] text-right lg:w-[229.56px]">
                        <h4 dir="auto" class="w-full text-base font-semibold leading-[20px] text-black dark:text-white">{{ $t('More about Botzo') }}</h4>
                        <ul class="flex w-full flex-col items-end gap-3">
                            <li v-for="item in footerMoreLinks" :key="item.href">
                                <Link :href="item.href" dir="auto" class="text-sm leading-6 text-[#8899aa] transition-colors hover:text-[#25d366]">{{ $t(item.labelKey) }}</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Product -->
                    <div class="flex w-full flex-col items-end gap-[18px] text-right lg:w-[229.56px]">
                        <h4 dir="auto" class="w-full text-base font-semibold leading-[20px] text-black dark:text-white">{{ $t('Product') }}</h4>
                        <ul class="flex w-full flex-col items-end gap-3">
                            <li v-for="item in footerProductLinks" :key="item.href">
                                <Link :href="item.href" dir="auto" class="text-sm leading-6 text-[#8899aa] transition-colors hover:text-[#25d366]">{{ $t(item.labelKey) }}</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Logo, tagline, socials -->
                    <div class="flex w-full flex-col items-end gap-4 lg:w-[367.3px]">
                        <div class="relative" style="width: 73.44px; height: 67px">
                            <img src="/images/footer/botzo-icon-dark.svg" alt="" class="absolute hidden dark:block" style="left: 17.49px; top: 0; width: 38.54px; height: 37.591px">
                            <img src="/images/footer/botzo-icon-light.svg" alt="" class="absolute block dark:hidden" style="left: 17.49px; top: 0; width: 38.54px; height: 37.591px">
                            <img src="/images/footer/botzo-word-dark.svg" alt="Botzo" class="absolute hidden dark:block" style="left: 0; top: 58.93%; width: 100%; height: 27.78%">
                            <img src="/images/footer/botzo-word-light.svg" alt="Botzo" class="absolute block dark:hidden" style="left: 0; top: 58.93%; width: 100%; height: 27.78%">
                            <p
                                class="absolute whitespace-nowrap font-sans not-italic text-[#25d366]"
                                style="left: 4.12px; top: 61.35px; font-size: 4.63px; letter-spacing: 0.1852px; line-height: normal"
                            >
                                <span>WhatsApp</span><span>{{ ' & ' }}</span><span>Meta</span><span>{{ ' Solutions' }}</span>
                            </p>
                        </div>
                        <p dir="auto" class="w-full text-right text-sm leading-6 text-[#8899aa]">
                            {{ $t('WhatsApp automation platform powered by AI for the Saudi market. Make WhatsApp sell for you — 24 hours a day.') }}
                        </p>
                        <div class="flex items-center justify-end gap-2 pt-1" dir="ltr">
                            <a
                                v-if="whatsappSocialLink"
                                :href="whatsappSocialLink"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="footer-social-icon footer-social-icon--whatsapp"
                                aria-label="WhatsApp"
                            >
                                <img src="/images/footer/whatsapp-glow.svg" class="footer-social-icon__glow" alt="" aria-hidden="true">
                                <img src="/images/footer/whatsapp-icon.svg" class="footer-social-icon__mark" alt="" aria-hidden="true">
                            </a>
                            <a
                                :href="linkedinUrl || '#'"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="footer-social-icon footer-social-icon--linkedin"
                                aria-label="LinkedIn"
                            >
                                <img src="/images/footer/linkedin-glow.svg" class="footer-social-icon__glow" alt="" aria-hidden="true">
                                <img src="/images/footer/linkedin-icon.svg" class="footer-social-icon__mark" alt="" aria-hidden="true">
                            </a>
                        </div>
                    </div>
                </div>

                <div class="w-full border-t-[0.5px] border-[#5a6b7e] pt-[28.5px] text-center dark:border-[#94a3b8]">
                    <p dir="auto" class="text-sm leading-6 text-[#8899aa]">
                        {{ $t('© {year} Botzo · All rights reserved · Made with ❤ for the Saudi market', { year: currentYear }) }}
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
    import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
    import { Link, usePage } from "@inertiajs/vue3";
    import { useRtl } from '@/Composables/useRtl';
    import CookieConsentBanner from '@/Components/CookieConsentBanner.vue';
    import NavBrandMark from '@/Components/NavBrandMark.vue';
    import LangToggle from '@/Components/LangToggle.vue';
    import ThemeToggle from '@/Components/ThemeToggle.vue';

    const props = defineProps(['companyConfig', 'pages']);

    const page = usePage();
    const currentYear = new Date().getFullYear();
    const { rtlClass, isRtl } = useRtl();
    
    const isAuthenticated = computed(() => {
        try {
            const auth = page.props.auth;
            return !!(auth && auth.user);
        } catch (error) {
            console.error('Auth check error:', error);
            return false;
        }
    });

    const linkedinUrl = ref(null);
    const isScrolled = ref(false);
    const showResourcesDropdown = ref(false);
    const showMobileLanguageDropdown = ref(false);
    const showMobileMenu = ref(false);
    const mobileMenuPanelAnimationClass = computed(() => (isRtl.value ? 'mobile-menu-panel-rtl' : 'mobile-menu-panel-ltr'));
    
    const currentLanguageCode = computed(() => {
        return page.props.currentLanguage || 'en';
    });

    const formattedName = computed(() => {
        return (value) => {
            return value.trim().toLowerCase().replace(/\s+/g, '-');
        };
    });

    const parseSocials = () => {
        if (!props.companyConfig || !props.companyConfig.socials) return;
        try {
            const socialsArray = JSON.parse(props.companyConfig.socials || '{}');
            linkedinUrl.value = socialsArray['linkedin'] || null;
        } catch (error) {
            console.error('Error parsing socials:', error);
        }
    };

    const whatsappSocialLink = computed(() => props.companyConfig?.book_a_demo_link || null);

    const footerMoreLinks = [
        { labelKey: 'Privacy Policy', href: '/privacy' },
        { labelKey: 'WhatsApp Account Verification', href: '/contact' },
        { labelKey: 'Terms of Use', href: '/terms-of-service' },
        { labelKey: 'Delete User Data', href: '/contact' },
        { labelKey: 'API Documentation', href: '/api-documentation' },
    ];

    const footerProductLinks = [
        { labelKey: 'Features', href: '/#section2' },
        { labelKey: 'Pricing', href: '/pricing' },
        { labelKey: 'How does it work?', href: '/#section3' },
        { labelKey: 'FAQs', href: '/faqs' },
        { labelKey: 'Contact Us', href: '/contact' },
    ];

    const handleScroll = () => {
        isScrolled.value = window.scrollY > 10;
    };

    // Prevent body scroll when mobile menu is open
    watch(showMobileMenu, (isOpen) => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    });

    onMounted(() => {
        parseSocials();
        window.addEventListener('scroll', handleScroll);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', handleScroll);
        // Clean up: ensure body scroll is restored
        document.body.style.overflow = '';
    });
</script>

<style scoped>
.footer-social-icon {
    position: relative;
    display: flex;
    height: 48px;
    width: 48px;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    border-radius: 12px;
    border: 0.8px solid rgba(216, 216, 216, 0.05);
    background-image: linear-gradient(135deg, rgba(248, 251, 255, 0.04) 0%, rgba(255, 255, 255, 0) 100%);
    backdrop-filter: blur(10px);
    box-shadow: 8px 4px 16px 0px rgba(0, 0, 0, 0.08);
    transition: transform 200ms ease;
}

.footer-social-icon::after {
    content: "";
    position: absolute;
    inset: -0.4px;
    border-radius: inherit;
    pointer-events: none;
}

.footer-social-icon--whatsapp::after {
    box-shadow: inset 0px 0px 8px 0px rgba(13, 252, 37, 0.32);
}

.footer-social-icon--linkedin::after {
    box-shadow: inset 0px 0px 8px 0px rgba(13, 137, 252, 0.32);
}

.footer-social-icon:hover {
    transform: translateY(-2px);
}

.footer-social-icon__glow {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    mix-blend-mode: plus-lighter;
    pointer-events: none;
}

.footer-social-icon__mark {
    position: relative;
    width: 32px;
    height: 32px;
}

.page-enter-anim {
    animation: pageEnter 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes pageEnter {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .page-enter-anim {
        animation: none;
    }
}

.dropdown-enter-active,
.dropdown-leave-active {
    transition: all 0.2s ease-out;
}

.dropdown-enter-from {
    opacity: 0;
    transform: translateY(-10px);
}

.dropdown-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

.dropdown-enter-to,
.dropdown-leave-from {
    opacity: 1;
    transform: translateY(0);
}

.fade-enter-active {
    transition: opacity 0.3s ease-out;
}

.fade-leave-active {
    transition: opacity 0.2s ease-in;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.fade-enter-active .mobile-menu-panel-ltr {
    animation: slideInLtr 0.3s ease-out;
}

.fade-leave-active .mobile-menu-panel-ltr {
    animation: slideOutLtr 0.2s ease-in;
}

.fade-enter-active .mobile-menu-panel-rtl {
    animation: slideInRtl 0.3s ease-out;
}

.fade-leave-active .mobile-menu-panel-rtl {
    animation: slideOutRtl 0.2s ease-in;
}

@keyframes slideInLtr {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutLtr {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(100%);
    }
}

@keyframes slideInRtl {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}

@keyframes slideOutRtl {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(100%);
    }
}
</style>
