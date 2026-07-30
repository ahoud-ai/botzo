<script setup>
import { computed, ref } from "vue";
import SectionBadge from "@/Components/SectionBadge.vue";
import PricingCard from "@/Components/PricingCard.vue";
import { usePricingPlans } from "@/Composables/usePricingPlans";

const props = defineProps({
    plans: { type: Array, default: () => [] },
    addons: { type: Object, default: () => ({}) },
    currency: { type: String, default: "SAR" },
});

const { getPlanName, planSubtitle, planHighlightFeatures, formatPrice, orderPlansByTier } = usePricingPlans(computed(() => props.addons));

const billingPeriod = ref("monthly");

const orderedPlans = computed(() => orderPlansByTier(props.plans, billingPeriod.value));
</script>

<template>
    <section
        v-if="orderedPlans.length"
        id="pricing"
        v-reveal
        class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
        <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
            <div class="flex w-full flex-col items-center gap-4 lg:w-[1120px] lg:gap-4">
                <SectionBadge :label="$t('Pricing')" />

                <div class="flex w-full flex-col items-center gap-3 text-center lg:gap-4">
                    <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t("Plans that fit the size of your business") }}
                    </h2>
                    <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t("In Saudi riyals, pay anytime, no binding contracts") }}
                    </p>
                </div>

                <div class="flex w-full flex-col items-center pt-2">
                    <div class="flex w-[240px] items-start rounded-full bg-[rgba(37,211,102,0.12)] p-1" dir="ltr">
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center self-stretch overflow-hidden rounded-full py-2 transition-all duration-200"
                            :class="billingPeriod === 'yearly' ? 'bg-white shadow-[0_2px_4px_-2px_rgba(0,0,0,0.12)] dark:bg-white' : ''"
                            @click="billingPeriod = 'yearly'"
                        >
                            <span
                                dir="auto"
                                class="whitespace-nowrap text-base leading-6"
                                :class="billingPeriod === 'yearly' ? 'text-black' : 'text-black dark:text-white'"
                            >{{ $t("Yearly") }}</span>
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center self-stretch overflow-hidden rounded-full py-2 transition-all duration-200"
                            :class="billingPeriod === 'monthly' ? 'bg-white shadow-[0_2px_4px_-2px_rgba(0,0,0,0.12)] dark:bg-white' : ''"
                            @click="billingPeriod = 'monthly'"
                        >
                            <span
                                dir="auto"
                                class="whitespace-nowrap text-base leading-6"
                                :class="billingPeriod === 'monthly' ? 'text-black' : 'text-black dark:text-white'"
                            >{{ $t("Monthly") }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex w-full flex-col items-center gap-6 lg:flex-row lg:items-end lg:justify-center lg:gap-6">
                <PricingCard
                    v-for="{ plan, featured } in orderedPlans"
                    :key="plan.id"
                    :featured="featured"
                    :top-label="getPlanName(plan)"
                    :price="formatPrice(plan.price)"
                    :period-label="billingPeriod === 'monthly' ? $t('/ month') : $t('/ year')"
                    :description="planSubtitle(plan)"
                    :features="planHighlightFeatures(plan)"
                    :button-label="featured ? $t('Subscribe now') : $t('Get Started')"
                />
            </div>
        </div>
    </section>
</template>
