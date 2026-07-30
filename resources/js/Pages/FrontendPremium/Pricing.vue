<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">

        <!-- Hero Section -->
        <section class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-60 pt-32 pb-8 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-4 lg:gap-6">
                <SectionBadge :label="$t('Pricing')" />

                <div class="flex w-full flex-col items-center gap-3 text-center lg:w-[1120px] lg:gap-4">
                    <h1 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Plans that fit the size of your business') }}
                    </h1>
                    <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Whether you\'re an individual, a small team, or a growing enterprise, we have a plan that aligns perfectly with your goals') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-3 pt-2">
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-[6px] bg-[rgba(37,211,102,0.12)]">
                            <span class="text-[11px] leading-[16.5px] text-[#0e6b35] dark:text-[#00c950]">✓</span>
                        </span>
                        <span dir="auto" class="text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">{{ $t('Paid plans') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-[6px] bg-[rgba(37,211,102,0.12)]">
                            <span class="text-[11px] leading-[16.5px] text-[#0e6b35] dark:text-[#00c950]">✓</span>
                        </span>
                        <span dir="auto" class="text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">{{ $t('Secure payment required') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-[6px] bg-[rgba(37,211,102,0.12)]">
                            <span class="text-[11px] leading-[16.5px] text-[#0e6b35] dark:text-[#00c950]">✓</span>
                        </span>
                        <span dir="auto" class="text-sm leading-6 text-[#445566] dark:text-[#94a3b8]">{{ $t('Cancel anytime') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
                <div class="flex w-full flex-col items-center pt-2">
                    <div class="flex w-[240px] items-start rounded-full bg-[rgba(37,211,102,0.12)] p-1" dir="ltr">
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center self-stretch overflow-hidden rounded-full py-2 transition-all duration-200"
                            :class="billingPeriod === 'yearly' ? 'bg-white shadow-[0_2px_4px_-2px_rgba(0,0,0,0.12)] dark:bg-white' : ''"
                            @click="setBillingPeriod('yearly')"
                        >
                            <span
                                dir="auto"
                                class="whitespace-nowrap text-base leading-6"
                                :class="billingPeriod === 'yearly' ? 'text-black' : 'text-black dark:text-white'"
                            >{{ $t('Yearly') }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center self-stretch overflow-hidden rounded-full py-2 transition-all duration-200"
                            :class="billingPeriod === 'monthly' ? 'bg-white shadow-[0_2px_4px_-2px_rgba(0,0,0,0.12)] dark:bg-white' : ''"
                            @click="setBillingPeriod('monthly')"
                        >
                            <span
                                dir="auto"
                                class="whitespace-nowrap text-base leading-6"
                                :class="billingPeriod === 'monthly' ? 'text-black' : 'text-black dark:text-white'"
                            >{{ $t('Monthly') }}</span>
                        </button>
                    </div>
                </div>

                <div class="flex w-full flex-col items-center gap-10 pt-6 lg:flex-row lg:items-end lg:justify-center lg:gap-6">
                    <PricingCard
                        v-for="{ plan, featured } in orderedPlans"
                        :key="plan.id"
                        :featured="featured"
                        :top-label="getPlanName(plan)"
                        :price="formatPrice(plan.price)"
                        :period-label="billingPeriod === 'monthly' ? $t('/ month') : $t('/ year')"
                        :description="planSubtitle(plan)"
                        :features="planFeatureList(plan)"
                        :button-label="featured ? $t('Subscribe now') : $t('Get Started')"
                    />
                </div>
            </div>
        </section>

        <!-- FAQs Section -->
        <section v-if="props.faqs && props.faqs.data && props.faqs.data.length > 0" class="premium-page-section px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="premium-page-content text-center">
                <div class="flex justify-center mb-2">
                    <div class="premium-page-badge mb-4">
                        <div class="flex items-center gap-2">
                            <div>{{ $t('FAQs') }}</div>
                        </div>
                    </div>
                </div>
                <h2 class="text-3xl md:text-5xl font-bold mb-4">{{ $t('Frequently asked questions') }}</h2>
                <div class="flex justify-center">
                    <h3 class="text-center text-[18px] mt-5 w-full max-w-2xl px-2 sm:px-0">{{ $t('Learn how our platform works and find solutions to common queries. Need more help? Reach out to our support team for personalized assistance') }}</h3>
                </div>
            </div>

            <div class="mt-14 lg:w-3/4 lg:mx-auto 2xl:w-2/3 space-y-2 md:px-20">
                <div v-for="(item, index) in props.faqs?.data" :key="index" class="premium-page-panel-soft premium-page-interactive overflow-hidden">
                    <button type="button" @click="selectedFaq = selectedFaq === index+1 ? null : index+1" class="inline-flex p-5 items-center justify-between w-full text-start transition">
                        <div class="font-medium text-gray-900" v-html="item.question"></div>
                        <div>
                            <svg v-if="selectedFaq != index+1" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M16.75 11.989a1.82 1.82 0 0 1-.57 1.36l-6.82 6.1a1.27 1.27 0 0 1-.65.31h-.19a1.3 1.3 0 0 1-.52-.1a1.23 1.23 0 0 1-.54-.47a1.19 1.19 0 0 1-.21-.68v-13a1.2 1.2 0 0 1 .21-.69a1.23 1.23 0 0 1 1.25-.56c.24.039.464.143.65.3l6.76 6.09c.19.162.344.363.45.59c.114.234.175.49.18.75"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M19.696 8.72a1.22 1.22 0 0 1-.3.64l-6.09 6.76a1.85 1.85 0 0 1-.58.46a1.7 1.7 0 0 1-1.42.03a1.75 1.75 0 0 1-.62-.42l-6.1-6.83a1.28 1.28 0 0 1-.31-.64a1.31 1.31 0 0 1 .56-1.26a1.36 1.36 0 0 1 .68-.21h13a1.293 1.293 0 0 1 1.15.76c.081.228.092.476.03.71"/></svg>
                        </div>
                    </button>
                    <div class="w-full overflow-hidden transition-[height] duration-300" :class="selectedFaq === index+1 ? '' : 'hidden'">
                        <div class="text-gray-600 pt-3 p-5" v-html="item.answer"></div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-14">
                <p class="inline-flex flex-wrap gap-1 bg-primary-opacity-10 text-sm text-primary rounded-full py-2 px-5">
                    {{ $t('Still have unanswered questions?') }}
                    <a :href="'mailto:' + props.companyConfig.email" class="hover:text-primary hover:underline transition-all">{{ $t('Contact Us') }}</a>
                </p>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="premium-page-section premium-page-cta px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="premium-page-content max-w-4xl mx-auto text-center">
                <h2 class="text-3xl md:text-5xl font-bold mb-6 text-gray-900">
                    {{ $t('Ready to Get Started?') }}
                </h2>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
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
    import { ref, computed } from 'vue';
    import { Link } from "@inertiajs/vue3";
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PricingCard from '@/Components/PricingCard.vue';
    import { usePricingPlans } from '@/Composables/usePricingPlans';

    const props = defineProps(['addons', 'enable_ai_billing', 'plans', 'companyConfig', 'currency', 'pages', 'faqs']);

    const {
        getPlanName,
        planSubtitle,
        planFeatureList,
        formatPrice,
        orderPlansByTier,
    } = usePricingPlans(computed(() => props.addons));

    const billingPeriod = ref('monthly');
    const selectedFaq = ref(null);

    const setBillingPeriod = (period) => {
        if (period === 'monthly' || period === 'yearly') {
            billingPeriod.value = period;
        }
    };

    const orderedPlans = computed(() => orderPlansByTier(props.plans, billingPeriod.value));
</script>


