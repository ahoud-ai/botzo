<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero Section -->
        <section class="relative px-5 md:px-10 lg:px-20 pt-32 pb-10 2xl:px-60 bg-gradient-section">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-3xl md:text-6xl leading-[1.3] font-semibold mb-6 text-gray-900">
                    {{ $t('Cookie Policy') }}
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Learn about how we use cookies and similar technologies on our website') }}
                </p>
            </div>
        </section>

        <!-- Cookie Policy Content -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="max-w-4xl mx-auto">
                <div class="prose prose-lg max-w-none">
                    <!-- Introduction -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('What Are Cookies?') }}</h2>
                        <p class="text-gray-700 leading-relaxed mb-4">
                            {{ $t('Cookies are small text files that are placed on your computer or mobile device when you visit a website. They are widely used to make websites work more efficiently and provide information to the website owners.') }}
                        </p>
                        <p class="text-gray-700 leading-relaxed">
                            {{ $t('This Cookie Policy explains how we use cookies and similar technologies on our website. By continuing to use our website, you consent to our use of cookies in accordance with this policy.') }}
                        </p>
                    </div>

                    <!-- Types of Cookies -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Types of Cookies We Use') }}</h2>
                        
                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Essential Cookies') }}</h3>
                            <p class="text-gray-700 leading-relaxed mb-2">
                                {{ $t('These cookies are necessary for the website to function properly. They enable core functionality such as security, network management, and accessibility. You cannot opt-out of these cookies as they are essential for the website to work.') }}
                            </p>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Analytics Cookies') }}</h3>
                            <p class="text-gray-700 leading-relaxed mb-2">
                                {{ $t('These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously. This helps us improve the website\'s performance and user experience.') }}
                            </p>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Functional Cookies') }}</h3>
                            <p class="text-gray-700 leading-relaxed mb-2">
                                {{ $t('These cookies enable the website to provide enhanced functionality and personalization. They may be set by us or by third-party providers whose services we have added to our pages.') }}
                            </p>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Marketing Cookies') }}</h3>
                            <p class="text-gray-700 leading-relaxed mb-2">
                                {{ $t('These cookies are used to track visitors across websites. The intention is to display ads that are relevant and engaging for individual users.') }}
                            </p>
                        </div>
                    </div>

                    <!-- How We Use Cookies -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('How We Use Cookies') }}</h2>
                        <ul class="list-disc ps-6 space-y-2 text-gray-700">
                            <li>{{ $t('To remember your preferences and settings') }}</li>
                            <li>{{ $t('To analyze how our website is used and improve its performance') }}</li>
                            <li>{{ $t('To provide personalized content and advertisements') }}</li>
                            <li>{{ $t('To ensure the security and functionality of our website') }}</li>
                            <li>{{ $t('To enable social media features') }}</li>
                        </ul>
                    </div>

                    <!-- Managing Cookies -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Managing Your Cookie Preferences') }}</h2>
                        <p class="text-gray-700 leading-relaxed mb-4">
                            {{ $t('You can control and manage cookies in various ways. Please keep in mind that removing or blocking cookies can impact your user experience and parts of our website may no longer be fully accessible.') }}
                        </p>
                        <p class="text-gray-700 leading-relaxed mb-4">
                            {{ $t('Most browsers automatically accept cookies, but you can modify your browser settings to decline cookies if you prefer. You can also use the cookie consent banner on our website to manage your preferences.') }}
                        </p>
                        <div class="bg-gray-100 rounded-lg p-4 border border-gray-200">
                            <p class="text-sm text-gray-600">
                                <strong>{{ $t('Note:') }}</strong> {{ $t('If you choose to disable cookies, some features of our website may not function properly.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Third-Party Cookies -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Third-Party Cookies') }}</h2>
                        <p class="text-gray-700 leading-relaxed mb-4">
                            {{ $t('In addition to our own cookies, we may also use various third-party cookies to report usage statistics of the website, deliver advertisements, and so on. These third parties may set their own cookies to collect information about your online activities across different websites.') }}
                        </p>
                    </div>

                    <!-- Updates -->
                    <div class="mb-12">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Updates to This Policy') }}</h2>
                        <p class="text-gray-700 leading-relaxed">
                            {{ $t('We may update this Cookie Policy from time to time to reflect changes in technology, legislation, or our business operations. We will notify you of any significant changes by posting the new policy on this page and updating the "Last Updated" date.') }}
                        </p>
                    </div>

                    <!-- Contact -->
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200">
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Contact Us') }}</h2>
                        <p class="text-gray-700 leading-relaxed mb-6">
                            {{ $t('If you have any questions about our use of cookies, please contact us.') }}
                        </p>
                        <div class="flex flex-col sm:flex-row gap-4">
                            <Link 
                                href="/contact" 
                                class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg text-sm font-medium transition-colors text-center"
                            >
                                {{ $t('Contact Us') }}
                            </Link>
                            <a 
                                v-if="props.companyConfig && props.companyConfig.email"
                                :href="'mailto:' + props.companyConfig.email" 
                                class="px-6 py-3 border border-gray-300 hover:border-primary text-gray-700 hover:text-primary rounded-lg text-sm font-medium transition-colors bg-white text-center"
                            >
                                {{ $t('Send Email') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { Link } from '@inertiajs/vue3';
    import FrontendLayout from './FrontendLayout.vue';

    const props = defineProps(['companyConfig', 'pages']);
</script>

<style scoped>
.prose h2 {
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.prose h3 {
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.prose p {
    margin-bottom: 1rem;
}

.prose ul {
    margin-top: 1rem;
    margin-bottom: 1rem;
}

.prose li {
    margin-bottom: 0.5rem;
}
</style>

