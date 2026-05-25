<template>
    <div :class="rtlClass">
        <!-- Sticky Header -->
        <header :class="['sticky top-0 ui-layer-content transition-all duration-300', isScrolled ? 'bg-white/80 backdrop-blur-md border-b border-gray-200' : 'bg-white border-b border-gray-200']">
            <div class="px-5 md:px-10 lg:px-20 2xl:px-60 py-4">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-8">
                        <Link href="/" class="premium-brand-lockup">
                            <template v-if="props.companyConfig && props.companyConfig.logo">
                                <img class="premium-brand-lockup__mark" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                                <span class="premium-brand-lockup__text">
                                    <span class="premium-brand-lockup__name"><span>botz</span><span class="premium-brand-lockup__accent">o</span></span>
                                </span>
                            </template>
                            <h1 v-else class="text-xl">{{ props.companyConfig?.company_name }}</h1>
                        </Link>
                        <div class="hidden lg:flex items-center text-sm gap-x-2">
                            <Link href="/product" class="cursor-pointer px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors" :class="page.url === '/product' ? 'bg-gray-100' : ''">{{ $t('Product') }}</Link>
                            <Link href="/pricing" class="cursor-pointer px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors" :class="page.url === '/pricing' ? 'bg-gray-100' : ''">{{ $t('Pricing') }}</Link>
                            <div class="relative" @mouseenter="showResourcesDropdown = true" @mouseleave="showResourcesDropdown = false">
                                <button type="button" class="cursor-pointer px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors flex items-center gap-1.5">
                                    {{ $t('Resources') }}
                                    <svg class="w-4 h-4 transition-transform duration-200" :class="showResourcesDropdown ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                                <transition name="dropdown">
                                    <div v-if="showResourcesDropdown" class="absolute top-full mt-2 bg-white border border-gray-200 rounded-2xl shadow-2xl py-6 px-6 w-[600px] max-w-[90vw] overflow-hidden ui-dropdown-layer ui-dropdown-start">
                                        <div class="grid grid-cols-2 gap-8">
                                            <!-- FAQs Column -->
                                            <div>
                                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ $t('Help & Support') }}</h3>
                                                <Link href="/faqs" class="group block p-3 rounded-lg hover:bg-gray-50 transition-all duration-150 mb-2">
                                                    <div class="font-medium text-gray-900 group-hover:text-gray-950">{{ $t('FAQs') }}</div>
                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $t('Find answers to common questions') }}</div>
                                                </Link>
                                                <Link href="/api-documentation" class="group block p-3 rounded-lg hover:bg-gray-50 transition-all duration-150">
                                                    <div class="font-medium text-gray-900 group-hover:text-gray-950">{{ $t('API Documentation') }}</div>
                                                    <div class="text-xs text-gray-500 mt-0.5">{{ $t('Integrate with our REST API') }}</div>
                                                </Link>
                                            </div>
                                            
                                            <!-- Pages Column -->
                                            <div v-if="props.pages && props.pages.length > 0">
                                                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-4">{{ $t('Pages') }}</h3>
                                                <div class="space-y-1">
                                                    <Link v-for="page in props.pages" :key="page.id" :href="'/pages/' + (page.slug || formattedName(page.name))" class="group block p-3 rounded-lg hover:bg-gray-50 transition-all duration-150">
                                                        <span class="text-sm font-medium text-gray-700 group-hover:text-gray-900">{{ page.display_name || page.name }}</span>
                                                    </Link>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </div>
                            <Link href="/contact" class="cursor-pointer px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors" :class="page.url === '/contact' ? 'bg-gray-100' : ''">{{ $t('Contact Us') }}</Link>
                        </div>
                    </div>
                    
                    <div class="flex justify-end items-center text-md gap-4">
                        <!-- Mobile Menu Button -->
                        <button type="button" @click="showMobileMenu = !showMobileMenu" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 transition-colors">
                            <svg v-if="!showMobileMenu" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <line x1="3" y1="12" x2="21" y2="12"></line>
                                <line x1="3" y1="18" x2="21" y2="18"></line>
                            </svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                        
                        <!-- Language Selector - Desktop -->
                        <div v-if="page.props.languages && page.props.languages.length > 1" class="hidden md:block relative" @mouseenter="showLanguageDropdown = true" @mouseleave="showLanguageDropdown = false">
                            <button type="button" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors text-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                </svg>
                                <span class="uppercase">{{ currentLanguageCode }}</span>
                                <svg class="w-4 h-4 transition-transform duration-200" :class="showLanguageDropdown ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"></polyline>
                                </svg>
                            </button>
                            <transition name="dropdown">
                                <div v-if="showLanguageDropdown" class="absolute top-full mt-2 bg-white border border-gray-200 rounded-lg shadow-lg py-2 min-w-[150px] ui-dropdown-layer ui-dropdown-end">
                                    <a 
                                        v-for="language in page.props.languages" 
                                        :key="language.id" 
                                        :href="'/language/' + language.code"
                                        class="block px-4 py-2 text-sm hover:bg-gray-50 transition-colors"
                                        :class="language.code === currentLanguageCode ? 'bg-gray-50 font-semibold text-primary' : 'text-gray-700'"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span>{{ language.name }}</span>
                                            <span v-if="language.code === currentLanguageCode" class="text-primary">✓</span>
                                        </div>
                                    </a>
                                </div>
                            </transition>
                        </div>
                        
                        <template v-if="!isAuthenticated">
                            <Link href="/login" class="hidden md:inline-block cursor-pointer hover:text-gray-400 border-black">{{ $t('Login') }}</Link>
                            <a v-if="props.companyConfig && props.companyConfig.book_a_demo_link" :href="props.companyConfig.book_a_demo_link" target="_blank" rel="noopener noreferrer" class="hidden md:inline-block border border-gray-300 hover:border-gray-400 text-gray-700 hover:text-gray-900 p-2 rounded-lg text-sm w-fit px-6 transition-colors bg-white shadow-md hover:shadow-lg">{{ $t('Book a demo') }}</a>
                            <Link href="/signup" class="hidden md:inline-block bg-primary hover:bg-secondary text-white p-2 rounded-lg text-sm w-fit px-8">{{ $t('Sign up') }}</Link>
                            <div class="lg:hidden flex items-center gap-2">
                                <Link href="/login" class="bg-primary hover:bg-secondary text-white p-2 rounded-lg text-sm flex w-fit px-8">{{ $t('Login') }}</Link>
                            </div>
                        </template>
                        <template v-else>
                            <Link href="/dashboard" class="hidden md:inline-block bg-primary hover:bg-secondary text-white p-2 rounded-lg text-sm w-fit px-8">{{ $t('Go to Dashboard') }}</Link>
                            <div class="lg:hidden flex items-center gap-2">
                                <Link href="/dashboard" class="bg-primary hover:bg-secondary text-white p-2 rounded-lg text-sm flex w-fit px-8">{{ $t('Go to Dashboard') }}</Link>
                            </div>
                        </template>
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
                <div :class="['absolute top-0 right-0 h-full w-80 max-w-[85vw] bg-white shadow-xl overflow-y-auto mobile-menu-panel', mobileMenuPanelAnimationClass]">
                    <div class="p-6">
                        <!-- Close Button -->
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-lg font-semibold text-gray-900">{{ $t('Menu') }}</h2>
                            <button type="button" @click="showMobileMenu = false" class="p-2 rounded-lg hover:bg-gray-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"></line>
                                    <line x1="6" y1="6" x2="18" y2="18"></line>
                                </svg>
                            </button>
                        </div>

                        <!-- Navigation Links -->
                        <nav class="space-y-2">
                            <Link href="/product" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors font-medium" :class="page.url === '/product' ? 'bg-gray-100 text-primary' : 'text-gray-700'">
                                {{ $t('Product') }}
                            </Link>
                            <Link href="/pricing" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors font-medium" :class="page.url === '/pricing' ? 'bg-gray-100 text-primary' : 'text-gray-700'">
                                {{ $t('Pricing') }}
                            </Link>
                            
                            <Link href="/faqs" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors font-medium" :class="page.url === '/faqs' ? 'bg-gray-100 text-primary' : 'text-gray-700'">
                                {{ $t('FAQs') }}
                            </Link>
                            <Link href="/api-documentation" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors font-medium" :class="page.url === '/api-documentation' ? 'bg-gray-100 text-primary' : 'text-gray-700'">
                                {{ $t('API Documentation') }}
                            </Link>
                            
                            <!-- Dynamic Pages -->
                            <Link 
                                v-for="pageItem in props.pages" 
                                :key="pageItem.id" 
                                :href="'/pages/' + (pageItem.slug || formattedName(pageItem.name))" 
                                @click="showMobileMenu = false"
                                class="block px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors font-medium"
                                :class="page.url === '/pages/' + (pageItem.slug || formattedName(pageItem.name)) ? 'bg-gray-100 text-primary' : 'text-gray-700'"
                            >
                                {{ pageItem.display_name || pageItem.name }}
                            </Link>
                            
                            <Link href="/contact" @click="showMobileMenu = false" class="block px-4 py-3 rounded-lg hover:bg-gray-100 transition-colors font-medium" :class="page.url === '/contact' ? 'bg-gray-100 text-primary' : 'text-gray-700'">
                                {{ $t('Contact Us') }}
                            </Link>
                        </nav>

                        <!-- Language Selector (Mobile) -->
                        <div v-if="page.props.languages && page.props.languages.length > 1" class="mt-6 pt-6 border-t border-gray-200">
                            <div class="relative">
                                <button type="button" 
                                    @click="showMobileLanguageDropdown = !showMobileLanguageDropdown"
                                    class="w-full flex items-center justify-between px-4 py-3 rounded-lg border border-gray-300 hover:bg-gray-50 transition-colors"
                                >
                                    <div class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="10"></circle>
                                            <line x1="2" y1="12" x2="22" y2="12"></line>
                                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                                        </svg>
                                        <span class="font-medium text-gray-900">{{ page.props.languages.find(l => l.code === currentLanguageCode)?.name || currentLanguageCode.toUpperCase() }}</span>
                                    </div>
                                    <svg class="w-4 h-4 transition-transform duration-200 text-gray-500" :class="showMobileLanguageDropdown ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                                <transition name="dropdown">
                                    <div v-if="showMobileLanguageDropdown" class="absolute top-full inset-x-0 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg py-2 max-h-60 overflow-y-auto ui-layer-dropdown">
                                        <a 
                                            v-for="language in page.props.languages" 
                                            :key="language.id" 
                                            :href="'/language/' + language.code"
                                            class="block px-4 py-2 text-sm transition-colors"
                                            :class="language.code === currentLanguageCode ? 'bg-gray-50 font-semibold text-primary' : 'text-gray-700 hover:bg-gray-50'"
                                            @click="showMobileLanguageDropdown = false"
                                        >
                                            <div class="flex items-center justify-between">
                                                <span>{{ language.name }}</span>
                                                <span v-if="language.code === currentLanguageCode" class="text-primary">✓</span>
                                            </div>
                                        </a>
                                    </div>
                                </transition>
                            </div>
                        </div>

                        <!-- Action Buttons (Mobile) -->
                        <div class="mt-6 pt-6 border-t border-gray-200 space-y-3">
                            <template v-if="!isAuthenticated">
                                <Link href="/login" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 border border-gray-300 hover:border-gray-400 text-gray-700 hover:text-gray-900 rounded-lg transition-colors font-medium">
                                    {{ $t('Login') }}
                                </Link>
                                <a v-if="props.companyConfig && props.companyConfig.book_a_demo_link" :href="props.companyConfig.book_a_demo_link" target="_blank" rel="noopener noreferrer" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 border border-gray-300 hover:border-gray-400 text-gray-700 hover:text-gray-900 rounded-lg transition-colors font-medium bg-white shadow-md">
                                    {{ $t('Book a demo') }}
                                </a>
                                <Link href="/signup" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 bg-primary hover:bg-secondary text-white rounded-lg transition-colors font-medium">
                                    {{ $t('Sign up') }}
                                </Link>
                            </template>
                            <template v-else>
                                <Link href="/dashboard" @click="showMobileMenu = false" class="block w-full text-center px-4 py-3 bg-primary hover:bg-secondary text-white rounded-lg transition-colors font-medium">
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
        <footer id="section7" class="bg-gray-50 border-t border-gray-200 text-gray-700 px-5 md:px-10 lg:px-20 py-16 2xl:px-60">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-8 mb-12">
                    <!-- Company Info -->
                    <div class="lg:col-span-3">
                        <div class="mb-6" v-if="props.companyConfig">
                            <div class="premium-brand-lockup premium-brand-lockup--footer" v-if="props.companyConfig.logo">
                                <img class="premium-brand-lockup__mark" :src="'/media/' + props.companyConfig.logo" :alt="props.companyConfig.company_name">
                                <span class="premium-brand-lockup__text">
                                    <span class="premium-brand-lockup__name"><span>botz</span><span class="premium-brand-lockup__accent">o</span></span>
                                </span>
                            </div>
                            <h4 v-else-if="props.companyConfig.company_name" class="text-xl mb-2 text-gray-900 font-bold">{{ props.companyConfig.company_name }}</h4>
                        </div>
                        <div v-if="addressLines.length" class="mb-4 space-y-1">
                            <p v-for="(addressLine, index) in addressLines" :key="`footer-company-address-${index}`" class="text-sm text-gray-600">
                                {{ addressLine }}
                            </p>
                        </div>
                        <div class="flex gap-x-4">
                            <a v-if="facebookUrl" :href="facebookUrl" class="w-10 h-10 rounded-lg bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-colors text-gray-600">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95"/></svg>
                            </a>
                            <a v-if="twitterUrl" :href="twitterUrl" class="w-10 h-10 rounded-lg bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-colors text-gray-600">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M18.244 2H21.5l-7.2 8.23L22.8 22h-6.65l-5.21-6.82L4.97 22H1.71l7.7-8.8L1.2 2h6.82l4.71 6.23zm-1.16 18h1.8L6.22 3.9H4.29z"/></svg>
                            </a>
                            <a v-if="linkedinUrl" :href="linkedinUrl" class="w-10 h-10 rounded-lg bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-colors text-gray-600">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16"><path fill="currentColor" d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248c-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586c.173-.431.568-.878 1.232-.878c.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252c-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg>
                            </a>
                            <a v-if="tiktokUrl" :href="tiktokUrl" class="w-10 h-10 rounded-lg bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-colors text-gray-600">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.35V2h-3.2v13.3a2.89 2.89 0 1 1-2.89-2.89c.31 0 .61.05.89.14V9.27a6.13 6.13 0 0 0-.89-.07A6.09 6.09 0 1 0 15.82 15V8.27a8.16 8.16 0 0 0 4.77 1.53V6.69z"/></svg>
                            </a>
                            <a v-if="snapchatUrl" :href="snapchatUrl" class="w-10 h-10 rounded-lg bg-white border border-gray-200 hover:bg-primary hover:border-primary hover:text-white flex items-center justify-center transition-colors text-gray-600">
                                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7v11l2-2l2 2l3-3l3 3l2-2l2 2V9a7 7 0 0 0-7-7m-3 8a1 1 0 1 1 .01 0zm6 0a1 1 0 1 1 .01 0z"/></svg>
                            </a>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-2 max-w-[300px]">
                            <div class="rounded-xl border border-gray-200 bg-white p-2.5 min-h-[84px] flex flex-col items-center justify-center text-center">
                                <img :src="premiumFooterPaymentMethodsUrl" :alt="$t('Payment methods')" class="h-10 w-full max-w-[220px] object-contain mb-1" @error="handleImageError($event, '/images/defaults/payment-methods-default.svg')">
                                <p class="text-sm font-medium text-gray-700 leading-5">{{ $t('We offer multiple payment methods') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Product Links -->
                    <div>
                        <h4 class="text-gray-900 font-semibold mb-4 text-sm uppercase tracking-wider">{{ $t('Product') }}</h4>
                        <ul class="space-y-3">
                            <li>
                                <Link href="/#section2" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ $t('Features') }}</Link>
                            </li>
                            <li>
                                <Link href="/pricing" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ $t('Pricing') }}</Link>
                            </li>
                            <li>
                                <Link href="/#section6" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ $t('FAQs') }}</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Company Links -->
                    <div>
                        <h4 class="text-gray-900 font-semibold mb-4 text-sm uppercase tracking-wider">{{ $t('Company') }}</h4>
                        <ul class="space-y-3">
                            <li>
                                <Link href="/contact" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ $t('Contact Us') }}</Link>
                            </li>
                            <li>
                                <Link href="/signup" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ $t('Sign up') }}</Link>
                            </li>
                            <li>
                                <Link href="/login" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ $t('Login') }}</Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Pages & Contact -->
                    <div>
                        <h4 class="text-gray-900 font-semibold mb-4 text-sm uppercase tracking-wider">{{ $t('Pages') }}</h4>
                        <ul class="space-y-3 mb-6" v-if="props.pages && props.pages.length > 0">
                            <li v-for="page in props.pages" :key="page.id">
                                <Link :href="'/pages/' + (page.slug || formattedName(page.name))" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ page.display_name || page.name }}</Link>
                            </li>
                        </ul>
                        <div>
                            <h4 class="text-gray-900 font-semibold mb-4 text-sm uppercase tracking-wider">{{ $t('Contact') }}</h4>
                            <ul class="space-y-2">
                                <li v-if="props.companyConfig && props.companyConfig.email">
                                    <a :href="'mailto:' + props.companyConfig.email" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ props.companyConfig.email }}</a>
                                </li>
                                <li v-for="phoneNumber in phoneNumbers" :key="`footer-contact-phone-${phoneNumber}`">
                                    <a :href="'tel:' + phoneNumber" class="text-gray-600 hover:text-primary transition-colors text-sm">{{ phoneNumber }}</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Bottom Bar -->
                <div class="border-t border-gray-200 pt-8 mt-8">
                    <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                        <p class="text-gray-600 text-sm">
                            {{ $t('Copyright') }} © {{ currentYear }} {{ props.companyConfig?.company_name || '' }}. {{ $t('All rights reserved') }}.
                        </p>
                        <Link href="/cookie-policy" class="text-gray-600 hover:text-primary transition-colors text-sm">
                            {{ $t('Cookie Policy') }}
                        </Link>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
    import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
    import { Link, usePage } from "@inertiajs/vue3";
    import { useRtl } from '@/Composables/useRtl';
    import { useFrontendContactInfo } from '@/Composables/useFrontendContactInfo';
    import CookieConsentBanner from '@/Components/CookieConsentBanner.vue';

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

    const facebookUrl = ref(null);
    const twitterUrl = ref(null);
    const tiktokUrl = ref(null);
    const snapchatUrl = ref(null);
    const linkedinUrl = ref(null);
    const isScrolled = ref(false);
    const showResourcesDropdown = ref(false);
    const showLanguageDropdown = ref(false);
    const showMobileLanguageDropdown = ref(false);
    const showMobileMenu = ref(false);
    const mobileMenuPanelAnimationClass = computed(() => (isRtl.value ? 'mobile-menu-panel-rtl' : 'mobile-menu-panel-ltr'));
    
    const currentLanguageCode = computed(() => {
        return page.props.currentLanguage || 'en';
    });
    const companyConfigRef = computed(() => props.companyConfig || {});
    const { addressLines, phoneNumbers } = useFrontendContactInfo(companyConfigRef, currentLanguageCode);
    const resolveManagedImageUrl = (value, fallback) => {
        if (!value || typeof value !== 'string') {
            return fallback;
        }

        return value.startsWith('http://') || value.startsWith('https://') || value.startsWith('/')
            ? value
            : `/media/${value}`;
    };
    const premiumFooterPaymentMethodsUrl = computed(() => {
        return resolveManagedImageUrl(props.companyConfig?.premium_home_footer_payment_methods, '/images/defaults/payment-methods-default.svg');
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
            facebookUrl.value = socialsArray['facebook'] || null;
            twitterUrl.value = socialsArray['twitter'] || null;
            tiktokUrl.value = socialsArray['tiktok'] || null;
            snapchatUrl.value = socialsArray['snapchat'] || null;
            linkedinUrl.value = socialsArray['linkedin'] || null;
        } catch (error) {
            console.error('Error parsing socials:', error);
        }
    };

    const handleScroll = () => {
        isScrolled.value = window.scrollY > 10;
    };

    const handleImageError = (event, fallback) => {
        if (!event?.target || event.target.src === `${window.location.origin}${fallback}`) {
            return;
        }

        event.target.src = fallback;
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
