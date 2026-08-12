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
                        <NavBrandMark class="hidden lg:block" variant="desktop" />
                        <NavBrandMark class="lg:hidden" variant="mobile" />
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
                                <div
                                    v-if="showResourcesDropdown"
                                    class="absolute top-full mt-2 flex w-[275px] flex-col items-end gap-2 rounded-2xl border border-[#cfd8e3] bg-white p-4 shadow-2xl dark:border-[#1a2332] dark:bg-[#0a0f17] ui-dropdown-layer ui-dropdown-start"
                                >
                                    <Link
                                        v-for="item in moreDropdownLinks"
                                        :key="item.href"
                                        :href="item.href"
                                        class="flex w-full items-center justify-center rounded-lg p-2 transition-colors hover:border-r hover:border-[#2bd46a] hover:bg-[rgba(37,211,102,0.12)]"
                                    >
                                        <span dir="auto" class="w-full flex-1 text-right text-base leading-6 text-black dark:text-white">{{ $t(item.labelKey) }}</span>
                                    </Link>
                                    <Link
                                        v-if="isAuthenticated"
                                        href="/logout"
                                        method="post"
                                        as="button"
                                        type="button"
                                        class="flex w-full items-center justify-center rounded-lg p-2 transition-colors hover:border-r hover:border-[#2bd46a] hover:bg-[rgba(37,211,102,0.12)]"
                                    >
                                        <span dir="auto" class="w-full flex-1 text-right text-base leading-6 text-[#f87171]">{{ $t('Logout') }}</span>
                                    </Link>
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
                            <Link href="/dashboard" class="hidden shrink-0 overflow-hidden rounded-full lg:inline-flex lg:h-11 lg:w-11">
                                <img v-if="authUser && authUser.avatar" :src="'/media/' + authUser.avatar" class="h-full w-full object-cover" alt="">
                                <span v-else class="flex h-full w-full items-center justify-center bg-[#25d366] text-[#04130a]">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="6" r="4"/><path stroke-linecap="round" d="M19.998 18c.002-.164.002-.331.002-.5c0-2.485-3.582-4.5-8-4.5s-8 2.015-8 4.5S4 22 12 22c2.231 0 3.84-.157 5-.437"/></g></svg>
                                </span>
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
                <div ref="mobileMenuPanel" :class="['absolute top-0 right-0 h-full w-80 max-w-[85vw] bg-white dark:bg-[#0a0f17] shadow-xl overflow-y-auto mobile-menu-panel', mobileMenuPanelAnimationClass]">
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
                        <NavBrandMark variant="desktop" />
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
    import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
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

    const authUser = computed(() => page.props.auth?.user ?? null);

    const linkedinUrl = ref(null);
    const isScrolled = ref(false);
    const showResourcesDropdown = ref(false);
    const showMobileLanguageDropdown = ref(false);
    const showMobileMenu = ref(false);
    const mobileMenuPanel = ref(null);
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

    // The static /privacy and /terms-of-service routes only work when the
    // Page record's `name` column happens to match one of a few guessed
    // slugs (see FrontendController::renderLegalPage) — when it doesn't,
    // they silently fall back to generic placeholder content instead of the
    // real page. Resolving directly against the already-loaded `pages` list
    // and linking to /pages/{slug} sidesteps that guessing entirely, and
    // works for pages (like Delete User Data) that have no dedicated route.
    const resolvePageHref = (candidateNames, fallbackHref) => {
        const pages = props.pages || [];
        const match = pages.find((page) => candidateNames.some(
            (candidate) => candidate.toLowerCase() === (page.name || '').toLowerCase()
        ));
        return match ? `/pages/${match.slug}` : fallbackHref;
    };

    const moreDropdownLinks = computed(() => [
        { labelKey: 'WhatsApp Account Verification', href: '/meta-verification' },
        { labelKey: 'Privacy Policy', href: resolvePageHref(['Privacy Policy', 'privacy-policy'], '/privacy') },
        { labelKey: 'Terms of Use', href: resolvePageHref(['Terms of Use', 'terms-of-service', 'terms-of-use'], '/terms-of-service') },
        { labelKey: 'Delete User Data', href: resolvePageHref(['User Data Deletion', 'Delete User Data', 'delete-user-data'], '/delete-user-data') },
        { labelKey: 'API Documentation', href: '/api-documentation' },
    ]);

    const footerMoreLinks = computed(() => [
        { labelKey: 'Privacy Policy', href: resolvePageHref(['Privacy Policy', 'privacy-policy'], '/privacy') },
        { labelKey: 'WhatsApp Account Verification', href: '/meta-verification' },
        { labelKey: 'Terms of Use', href: resolvePageHref(['Terms of Use', 'terms-of-service', 'terms-of-use'], '/terms-of-service') },
        { labelKey: 'Delete User Data', href: resolvePageHref(['User Data Deletion', 'Delete User Data', 'delete-user-data'], '/delete-user-data') },
        { labelKey: 'API Documentation', href: '/api-documentation' },
    ]);

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

    // Prevent body scroll when mobile menu is open. `overflow: hidden` alone
    // doesn't stop touch-scroll on mobile Safari, so the body is pinned with
    // `position: fixed` (offset by the current scroll position) instead, and
    // restored to that same scroll position on close.
    let mobileMenuScrollY = 0;
    watch(showMobileMenu, (isOpen) => {
        if (isOpen) {
            mobileMenuScrollY = window.scrollY;
            document.body.style.position = 'fixed';
            document.body.style.top = `-${mobileMenuScrollY}px`;
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.overflow = 'hidden';
            // Always open scrolled to the top, regardless of where it was left last time.
            nextTick(() => {
                if (mobileMenuPanel.value) mobileMenuPanel.value.scrollTop = 0;
            });
        } else {
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.overflow = '';
            window.scrollTo(0, mobileMenuScrollY);
        }
    });

    onMounted(() => {
        parseSocials();
        window.addEventListener('scroll', handleScroll);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', handleScroll);
        // Clean up: ensure body scroll is restored
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
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
