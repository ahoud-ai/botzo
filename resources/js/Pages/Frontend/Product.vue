<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero Section -->
        <section class="relative px-5 md:px-10 lg:px-20 pt-32 pb-20 2xl:px-60 bg-gradient-section">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-3xl md:text-6xl leading-[1.3] font-semibold mb-6 text-gray-900">
                    {{ $t('Everything You Need. One Platform.') }}
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Discover all the tools and capabilities that make our WhatsApp messaging platform the perfect solution for your business needs') }}
                </p>
                <div class="flex flex-wrap justify-center gap-4 text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                        <span>{{ $t('Paid plans') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                        <span>{{ $t('Secure payment required') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                        <span>{{ $t('Cancel anytime') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Main Features Grid -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="max-w-7xl mx-auto">
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Feature 1: WhatsApp Cloud API -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200 transition-all duration-300">
                        <div class="bg-secondary-opacity-10 rounded-xl flex items-center justify-center h-14 w-14 mb-6">
                            <svg class="h-7 w-7 text-secondary" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 26 26">
                                <g fill="currentColor">
                                    <path d="M8.05 17.95a1 1 0 1 1 1.414-1.415A5 5 0 0 0 18 13a1 1 0 1 1 2 0a7 7 0 0 1-11.95 4.95"/>
                                    <path d="M16.559 15.832a1 1 0 1 1-1.11-1.664l3-2a1 1 0 1 1 1.11 1.664z"/>
                                    <path d="M21.832 15.445a1 1 0 0 1-1.664 1.11l-2-3a1 1 0 1 1 1.664-1.11zm-3.975-7.594a1 1 0 1 1-1.414 1.414a5 5 0 0 0-8.536 3.536a1 1 0 1 1-2 0a7 7 0 0 1 11.95-4.95"/>
                                    <path d="M9.349 9.969a1 1 0 0 1 1.11 1.664l-3.001 2a1 1 0 1 1-1.11-1.664z"/>
                                    <path d="M4.075 10.356a1 1 0 1 1 1.664-1.11l2 3a1 1 0 1 1-1.664 1.11z"/>
                                    <path fill-rule="evenodd" d="M13 24c6.075 0 11-4.925 11-11S19.075 2 13 2S2 6.925 2 13s4.925 11 11 11m0 2c7.18 0 13-5.82 13-13S20.18 0 13 0S0 5.82 0 13s5.82 13 13 13" clip-rule="evenodd"/>
                                </g>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('WhatsApp Cloud API') }}</h3>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $t('Connect directly to Meta\'s WhatsApp Cloud API for reliable, scalable messaging with enterprise-grade infrastructure') }}
                        </p>
                    </div>

                    <!-- Feature 2: Message Templates -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200 transition-all duration-300">
                        <div class="bg-secondary-opacity-10 rounded-xl flex items-center justify-center h-14 w-14 mb-6">
                            <svg class="h-7 w-7 text-secondary" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Message Templates') }}</h3>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $t('Create and manage WhatsApp-approved message templates for alerts, updates, and campaigns') }}
                        </p>
                    </div>

                    <!-- Feature 3: Rich Media Support -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200 transition-all duration-300">
                        <div class="bg-secondary-opacity-10 rounded-xl flex items-center justify-center h-14 w-14 mb-6">
                            <svg class="h-7 w-7 text-secondary" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M9 13V5c0-1.1.9-2 2-2h9c1.1 0 2 .9 2 2v6h-3.43l-1.28-1.74a.14.14 0 0 0-.24 0L15.06 12c-.06.06-.18.07-.24 0l-1.43-1.75a.152.152 0 0 0-.23 0l-2.11 2.66c-.08.09-.01.24.11.24h6.34V15H11c-1.11 0-2-.89-2-2m-3 9v-1H4v1H2V2h2v1h2V2h2.39C7.54 2.74 7 3.8 7 5v8c0 2.21 1.79 4 4 4h4.7c-1.03.83-1.7 2.08-1.7 3.5c0 .53.11 1.03.28 1.5zM4 7h2V5H4zm0 4h2V9H4zm0 4h2v-2H4zm2 4v-2H4v2zm17-6v2h-2v5.5a2.5 2.5 0 0 1-5 0a2.5 2.5 0 0 1 3.5-2.29V13z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Rich Media Support') }}</h3>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $t('Send images, videos, documents, audio files, and location sharing through WhatsApp\'s native media capabilities') }}
                        </p>
                    </div>

                    <!-- Feature 4: Two-Way Messaging -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200 transition-all duration-300">
                        <div class="bg-secondary-opacity-10 rounded-xl flex items-center justify-center h-14 w-14 mb-6">
                            <svg class="h-7 w-7 text-secondary" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 1024 1024">
                                <path fill="currentColor" d="M288 384h448v64H288zm96-128h256v64H384zM131.456 512H384v128h256V512h252.544L721.856 192H302.144zM896 576H704v128H320V576H128v256h768zM275.776 128h472.448a32 32 0 0 1 28.608 17.664l179.84 359.552A32 32 0 0 1 960 519.552V864a32 32 0 0 1-32 32H96a32 32 0 0 1-32-32V519.552a32 32 0 0 1 3.392-14.336l179.776-359.552A32 32 0 0 1 275.776 128"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Two-Way Messaging') }}</h3>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $t('Receive and respond to customer messages in real-time with full support for inbound and outbound conversations') }}
                        </p>
                    </div>

                    <!-- Feature 5: Automated Replies -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200 transition-all duration-300">
                        <div class="bg-secondary-opacity-10 rounded-xl flex items-center justify-center h-14 w-14 mb-6">
                            <svg class="h-7 w-7 text-secondary" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M18.5 10.255c0 .044 0 .089-.003.133A1.537 1.537 0 0 0 17.473 10c-.162 0-.32.025-.473.074V5.75a.75.75 0 0 0-.75-.75h-8.5a.75.75 0 0 0-.75.75v4.505c0 .414.336.75.75.75h8.276l-.01.025l-.003.012l-.45 1.384l-.01.026a1.625 1.625 0 0 1-.019.053H7.75a2.25 2.25 0 0 1-2.25-2.25V5.75A2.25 2.25 0 0 1 7.75 3.5h3.5v-.75a.75.75 0 0 1 .649-.743L12 2a.75.75 0 0 1 .743.649l.007.101l-.001.75h3.5a2.25 2.25 0 0 1 2.25 2.25zm-5.457 3.781l.112-.036H6.254a2.25 2.25 0 0 0-2.25 2.25v.907a3.75 3.75 0 0 0 1.305 2.844c1.563 1.343 3.802 2 6.691 2c2.076 0 3.817-.339 5.213-1.028a1.545 1.545 0 0 1-1.169-1.003l-.004-.012l-.03-.093c-1.086.422-2.42.636-4.01.636c-2.559 0-4.455-.556-5.713-1.638a2.25 2.25 0 0 1-.783-1.706v-.907a.75.75 0 0 1 .75-.75H12v-.003a1.543 1.543 0 0 1 1.031-1.456zM10.999 7.75a1.25 1.25 0 1 0-2.499 0a1.25 1.25 0 0 0 2.499 0m3.243-1.25a1.25 1.25 0 1 1 0 2.499a1.25 1.25 0 0 1 0-2.499m1.847 10.912a2.831 2.831 0 0 0-1.348-.955l-1.377-.448a.544.544 0 0 1 0-1.025l1.377-.448a2.84 2.84 0 0 0 1.76-1.762l.01-.034l.449-1.377a.544.544 0 0 1 1.026 0l.448 1.377a2.837 2.837 0 0 0 1.798 1.796l1.378.448l.027.007a.544.544 0 0 1 0 1.025l-1.378.448a2.839 2.839 0 0 0-1.798 1.796l-.447 1.377a.55.55 0 0 1-.2.263a.544.544 0 0 1-.827-.263l-.448-1.377a2.834 2.834 0 0 0-.45-.848m7.694 3.801l-.765-.248a1.577 1.577 0 0 1-.999-.998l-.249-.765a.302.302 0 0 0-.57 0l-.249.764a1.577 1.577 0 0 1-.983.999l-.766.248a.302.302 0 0 0 0 .57l.766.249a1.576 1.576 0 0 1 .999 1.002l.248.764a.303.303 0 0 0 .57 0l.25-.764a1.575 1.575 0 0 1 .998-.999l.766-.248a.302.302 0 0 0 0-.57z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Automated Replies') }}</h3>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $t('Set up intelligent automated responses using canned replies and AI-powered assistants to handle customer inquiries instantly, even outside business hours') }}
                        </p>
                    </div>

                    <!-- Feature 6: Contact Management -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200 transition-all duration-300">
                        <div class="bg-secondary-opacity-10 rounded-xl flex items-center justify-center h-14 w-14 mb-6">
                            <svg class="h-7 w-7 text-secondary" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24">
                                <path fill="currentColor" d="M12 12q-1.65 0-2.825-1.175T8 8t1.175-2.825T12 4t2.825 1.175T16 8t-1.175 2.825T12 12m-8 8v-2.8q0-.85.438-1.562T5.6 14.55q1.55-.775 3.15-1.162T12 13t3.25.388t3.15 1.162q.725.375 1.163 1.088T20 17.2V20z"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Contact Management') }}</h3>
                        <p class="text-gray-600 leading-relaxed">
                            {{ $t('Organize and manage your WhatsApp contacts with groups, tags, and custom fields for targeted messaging campaigns') }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feature Highlights Section -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60 bg-gray-50">
            <div class="max-w-7xl mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                        {{ $t('Everything You Need to Succeed') }}
                    </h2>
                    <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                        {{ $t('Our platform provides comprehensive tools and features designed to help your business communicate effectively and grow') }}
                    </p>
                </div>

                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div>
                        <img :src="dashboard3Image" :alt="$t('Dashboard')" class="rounded-2xl">
                    </div>
                    <div>
                        <h3 class="text-2xl font-semibold text-gray-900 mb-6">{{ $t('Intuitive Dashboard') }}</h3>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <svg class="h-6 w-6 text-secondary flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span class="text-gray-700">{{ $t('Real-time message monitoring and management') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="h-6 w-6 text-secondary flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span class="text-gray-700">{{ $t('Advanced analytics and reporting tools') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="h-6 w-6 text-secondary flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span class="text-gray-700">{{ $t('Customizable workflows and automation') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <svg class="h-6 w-6 text-secondary flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="20 6 9 17 4 12"></polyline>
                                </svg>
                                <span class="text-gray-700">{{ $t('Contact management and segmentation') }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60 bg-gradient-cta">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl md:text-5xl font-semibold text-gray-900 mb-6">
                    {{ $t('Ready to Get Started?') }}
                </h2>
                <p class="text-xl text-gray-600 mb-10 max-w-2xl mx-auto">
                    {{ $t('Join thousands of businesses already using our platform to streamline their communication and grow their customer base.') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link href="/signup" class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-xl text-base font-semibold transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-105">
                        {{ $t('Subscribe now') }}
                    </Link>
                    <a v-if="props.companyConfig && props.companyConfig.book_a_demo_link" :href="props.companyConfig.book_a_demo_link" target="_blank" rel="noopener noreferrer" class="px-6 py-3 border-2 border-gray-300 hover:border-primary text-gray-700 hover:text-primary rounded-xl text-base font-semibold transition-all duration-300 bg-white">
                        {{ $t('Book a Demo') }}
                    </a>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import FrontendLayout from './FrontendLayout.vue';

    const props = defineProps(['companyConfig', 'pages']);
    
    // Image path as computed property to prevent Vite from trying to resolve it during build
    const dashboard3Image = computed(() => '/images/hero/dashboard3.png');
</script>
