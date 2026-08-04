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
        <section v-if="props.faqs && props.faqs.data && props.faqs.data.length > 0" class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-10 lg:gap-[72px]">
                <div class="flex w-full flex-col items-center gap-3 text-center lg:w-[1120px] lg:gap-4">
                    <SectionBadge :label="$t('FAQs')" />
                    <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Frequently asked questions') }}
                    </h2>
                    <p dir="auto" class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Learn how our platform works and find solutions to common queries. Need more help? Reach out to our support team for personalized assistance') }}
                    </p>
                </div>

                <div class="w-full lg:w-[760px]">
                    <FaqAccordion :items="props.faqs.data" />
                </div>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex w-full flex-col items-center gap-6 overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] px-6 py-12 text-center md:px-10 lg:px-20 lg:py-20">
                <div class="flex w-full flex-col items-center gap-4 lg:w-[1120px] lg:gap-4">
                    <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Ready to Get Started?') }}
                    </h2>
                    <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Join thousands of businesses already using our platform to streamline their communication and grow their customer base.') }}
                    </p>
                </div>
                <div class="flex w-full flex-col items-center gap-3 sm:w-auto sm:flex-row" dir="ltr">
                    <Link
                        href="/signup"
                        class="flex h-[54px] w-full items-center justify-center rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03] sm:w-[200px]"
                        style="background-image: linear-gradient(135.79deg, #25d366 0%, #1db954 100%)"
                    >
                        <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Subscribe now') }}</span>
                    </Link>
                    <a
                        v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
                        :href="props.companyConfig.book_a_demo_link"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex h-[54px] w-full items-center justify-center rounded-2xl border border-[#1a2332] px-8 transition-colors hover:border-[#25d366] dark:border-white sm:w-[200px]"
                    >
                        <span dir="auto" class="whitespace-nowrap text-base font-semibold leading-5 text-black dark:text-white">{{ $t('Book a Demo') }}</span>
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
    import FaqAccordion from '@/Components/FaqAccordion.vue';
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

    const setBillingPeriod = (period) => {
        if (period === 'monthly' || period === 'yearly') {
            billingPeriod.value = period;
        }
    };

    const orderedPlans = computed(() => orderPlansByTier(props.plans, billingPeriod.value));
</script>


