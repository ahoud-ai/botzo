<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-32 pt-32 pb-8 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-4 lg:gap-6">
                <PageBreadcrumb :current-label="$t('Cookie Policy')" />

                <div class="flex w-full flex-col items-center gap-3 text-center lg:w-[1280px] lg:gap-4">
                    <SectionBadge :label="$t('Cookie Policy')" />
                    <h1 class="w-full text-[28px] font-semibold leading-[1.3] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Cookie Policy') }}
                    </h1>
                    <p dir="auto" class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Learn about how we use cookies and similar technologies on our website') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Cookie Policy Content -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-32 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-10">
                <div dir="auto" class="page-content w-full text-right lg:w-[1280px]">
                    <!-- Introduction -->
                    <div class="mb-12">
                        <h2>{{ $t('What Are Cookies?') }}</h2>
                        <p>
                            {{ $t('Cookies are small text files that are placed on your computer or mobile device when you visit a website. They are widely used to make websites work more efficiently and provide information to the website owners.') }}
                        </p>
                        <p>
                            {{ $t('This Cookie Policy explains how we use cookies and similar technologies on our website. By continuing to use our website, you consent to our use of cookies in accordance with this policy.') }}
                        </p>
                    </div>

                    <!-- Types of Cookies -->
                    <div class="mb-12">
                        <h2>{{ $t('Types of Cookies We Use') }}</h2>

                        <h3>{{ $t('Essential Cookies') }}</h3>
                        <p>
                            {{ $t('These cookies are necessary for the website to function properly. They enable core functionality such as security, network management, and accessibility. You cannot opt-out of these cookies as they are essential for the website to work.') }}
                        </p>

                        <h3>{{ $t('Analytics Cookies') }}</h3>
                        <p>
                            {{ $t('These cookies help us understand how visitors interact with our website by collecting and reporting information anonymously. This helps us improve the website\'s performance and user experience.') }}
                        </p>

                        <h3>{{ $t('Functional Cookies') }}</h3>
                        <p>
                            {{ $t('These cookies enable the website to provide enhanced functionality and personalization. They may be set by us or by third-party providers whose services we have added to our pages.') }}
                        </p>

                        <h3>{{ $t('Marketing Cookies') }}</h3>
                        <p>
                            {{ $t('These cookies are used to track visitors across websites. The intention is to display ads that are relevant and engaging for individual users.') }}
                        </p>
                    </div>

                    <!-- How We Use Cookies -->
                    <div class="mb-12">
                        <h2>{{ $t('How We Use Cookies') }}</h2>
                        <ul>
                            <li>{{ $t('To remember your preferences and settings') }}</li>
                            <li>{{ $t('To analyze how our website is used and improve its performance') }}</li>
                            <li>{{ $t('To provide personalized content and advertisements') }}</li>
                            <li>{{ $t('To ensure the security and functionality of our website') }}</li>
                            <li>{{ $t('To enable social media features') }}</li>
                        </ul>
                    </div>

                    <!-- Managing Cookies -->
                    <div class="mb-12">
                        <h2>{{ $t('Managing Your Cookie Preferences') }}</h2>
                        <p>
                            {{ $t('You can control and manage cookies in various ways. Please keep in mind that removing or blocking cookies can impact your user experience and parts of our website may no longer be fully accessible.') }}
                        </p>
                        <p>
                            {{ $t('Most browsers automatically accept cookies, but you can modify your browser settings to decline cookies if you prefer. You can also use the cookie consent banner on our website to manage your preferences.') }}
                        </p>
                        <div class="note-box">
                            <p class="!mt-0">
                                <strong>{{ $t('Note:') }}</strong> {{ $t('If you choose to disable cookies, some features of our website may not function properly.') }}
                            </p>
                        </div>
                    </div>

                    <!-- Third-Party Cookies -->
                    <div class="mb-12">
                        <h2>{{ $t('Third-Party Cookies') }}</h2>
                        <p>
                            {{ $t('In addition to our own cookies, we may also use various third-party cookies to report usage statistics of the website, deliver advertisements, and so on. These third parties may set their own cookies to collect information about your online activities across different websites.') }}
                        </p>
                    </div>

                    <!-- Updates -->
                    <div>
                        <h2>{{ $t('Updates to This Policy') }}</h2>
                        <p>
                            {{ $t('We may update this Cookie Policy from time to time to reflect changes in technology, legislation, or our business operations. We will notify you of any significant changes by posting the new policy on this page and updating the "Last Updated" date.') }}
                        </p>
                    </div>
                </div>

                <!-- Contact CTA -->
                <div class="flex w-full flex-col items-center gap-6 overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] px-6 py-12 text-center md:px-10 lg:w-[1280px] lg:py-16">
                    <div class="flex w-full flex-col items-center gap-3">
                        <h2 class="w-full text-xl font-semibold text-black dark:text-white lg:text-[32px]">{{ $t('Contact Us') }}</h2>
                        <p dir="auto" class="w-full max-w-[520px] text-sm leading-6 text-[#8899aa] lg:text-base">
                            {{ $t('If you have any questions about our use of cookies, please contact us.') }}
                        </p>
                    </div>
                    <div class="flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row" dir="ltr">
                        <Link
                            href="/contact"
                            class="flex h-[54px] w-full items-center justify-center rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03] sm:w-[200px]"
                            style="background-image: linear-gradient(135.79deg, #25d366 0%, #1db954 100%)"
                        >
                            <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Contact Us') }}</span>
                        </Link>
                        <a
                            v-if="props.companyConfig && props.companyConfig.email"
                            :href="'mailto:' + props.companyConfig.email"
                            class="flex h-[54px] w-full items-center justify-center rounded-2xl border border-[#1a2332] px-8 transition-colors hover:border-[#25d366] dark:border-white sm:w-[200px]"
                        >
                            <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-black dark:text-white">{{ $t('Send Email') }}</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { Link } from '@inertiajs/vue3';
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PageBreadcrumb from '@/Components/PageBreadcrumb.vue';

    const props = defineProps(['companyConfig', 'pages']);
</script>

<style scoped>
.page-content :deep(p) {
    font-size: 1rem;
    line-height: 1.6;
    color: #445566;
    margin-top: 1rem;
}

.page-content :deep(li) {
    font-size: 1rem;
    line-height: 1.6;
    color: #445566;
}

.page-content :deep(h2) {
    font-weight: 700;
    font-size: 1.5rem;
    color: #000000;
    margin-bottom: 1rem;
}

.page-content :deep(h3) {
    font-weight: 600;
    font-size: 1.25rem;
    color: #000000;
    margin-top: 1.5rem;
    margin-bottom: 0.75rem;
}

.page-content :deep(ul) {
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding-inline-start: 1.5rem;
    list-style-type: disc;
}

.page-content :deep(li) {
    margin-bottom: 0.5rem;
}

.page-content :deep(strong) {
    font-weight: 600;
    color: #000000;
}

.page-content :deep(a) {
    color: #25d366;
    text-decoration: underline;
}

.page-content :deep(a:hover) {
    color: #1db954;
}

.note-box {
    margin-top: 1rem;
    border-radius: 0.75rem;
    border: 1px solid #cfd8e3;
    background: #f7f9fb;
    padding: 1rem;
}

:global(.dark) .page-content :deep(p),
:global(.dark) .page-content :deep(li) {
    color: #94a3b8;
}

:global(.dark) .page-content :deep(h2),
:global(.dark) .page-content :deep(h3),
:global(.dark) .page-content :deep(strong) {
    color: #ffffff;
}

:global(.dark) .note-box {
    border-color: #1e2a3a;
    background: #0d1117;
}
</style>
