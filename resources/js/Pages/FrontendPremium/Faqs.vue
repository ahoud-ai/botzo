<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 pt-32 pb-8 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-4 lg:gap-6">
                <PageBreadcrumb :current-label="$t('FAQs')" />

                <div class="flex w-full flex-col items-center gap-3 text-center lg:w-[1120px] lg:gap-4">
                    <SectionBadge :label="$t('FAQs')" />
                    <h1 class="w-full text-[28px] font-semibold leading-[1.3] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Everything you want to know') }}
                    </h1>
                    <p dir="auto" class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Find answers to common questions about our platform, features, and how to get started') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- FAQ list -->
        <section v-if="props.faqs && props.faqs.data && props.faqs.data.length > 0" class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-10 lg:gap-[72px]">
                <div class="w-full lg:w-[760px]">
                    <FaqAccordion :items="props.faqs.data" />
                </div>

                <!-- Still have questions CTA -->
                <div class="flex w-full flex-col items-center gap-6 overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] px-6 py-12 text-center md:px-10 lg:w-[1120px] lg:py-16">
                    <div class="flex w-full flex-col items-center gap-3">
                        <h2 class="w-full text-xl font-semibold text-black dark:text-white lg:text-[32px]">{{ $t('Still have questions?') }}</h2>
                        <p dir="auto" class="w-full max-w-[520px] text-sm leading-6 text-[#8899aa] lg:text-base">
                            {{ $t("Can't find the answer you're looking for? Our support team is here to help.") }}
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

        <!-- Empty state -->
        <section v-else class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="mx-auto flex w-full flex-col items-center gap-4 rounded-3xl border border-[#cfd8e3] bg-white p-12 text-center dark:border-[#1e2a3a] dark:bg-[#0a0f17] lg:w-[760px]">
                <svg class="h-12 w-12 text-[#8899aa]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <h3 dir="auto" class="text-xl font-semibold text-black dark:text-white">{{ $t('No FAQs Available') }}</h3>
                <p dir="auto" class="text-sm leading-6 text-[#8899aa]">{{ $t("We're currently updating our FAQ section. Please contact us if you have any questions.") }}</p>
                <Link
                    href="/contact"
                    class="flex h-12 items-center justify-center rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03]"
                    style="background-image: linear-gradient(135.79deg, #25d366 0%, #1db954 100%)"
                >
                    <span dir="auto" class="whitespace-nowrap text-sm font-semibold text-[#04130a]">{{ $t('Contact Us') }}</span>
                </Link>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { Link } from '@inertiajs/vue3';
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PageBreadcrumb from '@/Components/PageBreadcrumb.vue';
    import FaqAccordion from '@/Components/FaqAccordion.vue';

    const props = defineProps(['companyConfig', 'pages', 'faqs']);
</script>
