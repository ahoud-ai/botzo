<template>
  <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
    <section
      id="section1"
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center justify-center gap-12 lg:gap-[72px]">
        <!-- CTA Section -->
        <div class="flex w-full flex-col items-center gap-4 lg:w-[598px] lg:gap-6">
          <div class="flex w-full flex-col items-center gap-2 text-center lg:gap-4">
            <h1 class="font-semibold text-[24px] leading-[29.9px] text-black dark:text-white lg:text-[64px] lg:leading-[1.4]">
              {{ $t("WhatsApp works even while you sleep.") }}
            </h1>
            <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
              {{ $t("Turn WhatsApp into a smart sales agent that replies, follows up, and closes deals around the clock.") }}
            </p>
          </div>

          <a
            v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
            :href="props.companyConfig.book_a_demo_link"
            target="_blank"
            rel="noopener noreferrer"
            class="flex h-[54px] w-[246px] shrink-0 items-center justify-center gap-2 rounded-2xl px-8"
            style="background-image: linear-gradient(139deg, #25d366 0%, #1db954 100%)"
          >
            <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t("Book a demo") }}</span>
            <img src="/images/hero/arrow.svg" class="h-4 w-4 shrink-0 rotate-180 rtl:rotate-0" alt="" aria-hidden="true" />
          </a>
          <Link
            v-else
            href="/contact"
            class="flex h-[54px] w-[246px] shrink-0 items-center justify-center gap-2 rounded-2xl px-8"
            style="background-image: linear-gradient(139deg, #25d366 0%, #1db954 100%)"
          >
            <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t("Book a demo") }}</span>
            <img src="/images/hero/arrow.svg" class="h-4 w-4 shrink-0 rotate-180 rtl:rotate-0" alt="" aria-hidden="true" />
          </Link>
        </div>

        <!-- Overview illustration: Figma only designs this for desktop/tablet; the mobile Hero frame is CTA-only -->
        <HeroChatDemo />
      </div>
    </section>

    <section
      id="section2"
      v-reveal
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
        <!-- Header -->
        <div class="flex w-full flex-col items-center gap-3 lg:gap-4">
          <SectionBadge :label="$t('Features')" />
          <div class="flex w-full flex-col items-center gap-2 text-center lg:w-[1120px] lg:gap-4">
            <h2 class="font-semibold text-[22px] leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
              {{ $t("Everything you need to turn WhatsApp into a sales machine") }}
            </h2>
            <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
              {{ $t("Features built to double your sales and cut your work hours.") }}
            </p>
          </div>
        </div>

        <!-- Desktop/tablet: KPI + bot-workflow row, then 3 feature cards -->
        <div class="hidden flex-col items-end gap-6 lg:flex lg:w-[1120px]">
          <div class="flex items-center gap-4" dir="ltr">
            <!-- KPI card -->
            <div class="flex h-[408px] w-[362px] shrink-0 flex-col items-end justify-center gap-6 overflow-hidden rounded-3xl border-[0.7px] border-[#cfd8e3] bg-white p-[24.7px] dark:border-[#1e2a3a] dark:bg-[#0a0f17]">
              <div class="flex h-[120.8px] w-full items-end justify-center gap-[9px] pt-[0.8px]">
                <div class="h-[110.39px] flex-1 rounded-t-[6px] bg-[#25d366]"></div>
                <div class="h-[79.19px] flex-1 rounded-t-[6px] bg-[#25d366]"></div>
                <div class="h-[88.8px] flex-1 rounded-t-[6px] bg-[#25d366]"></div>
                <div class="h-[60px] flex-1 rounded-t-[6px] bg-[#25d366]"></div>
                <div class="h-[69.59px] flex-1 rounded-t-[6px] bg-[#25d366]"></div>
                <div class="h-[50.39px] flex-1 rounded-t-[6px] bg-[#25d366]"></div>
              </div>
              <div class="flex w-full flex-col items-end gap-3">
                <p
                  class="w-full whitespace-nowrap bg-clip-text text-right text-[64px] font-semibold leading-[1.4] text-transparent"
                  style="background-image: linear-gradient(103deg, #25d366 0%, #3b82f6 100%)"
                  dir="auto"
                >
                  ↑ {{ $t("31%") }}
                </p>
                <div class="flex w-full flex-col items-end gap-2 text-right">
                  <h3 class="w-full text-[24px] font-semibold leading-[29.9px] text-black dark:text-white" dir="auto">
                    {{ $t("Live statistics") }}
                  </h3>
                  <p class="w-full text-base leading-6 text-[#8899aa]" dir="auto">
                    {{ $t("Track reply rates, conversions, and peak times in a live dashboard that updates every second.") }}
                  </p>
                </div>
              </div>
            </div>

            <!-- Bot workflow card -->
            <div
              class="flex h-[408px] w-[742px] shrink-0 flex-col items-end gap-6 overflow-hidden rounded-3xl border-[0.7px] border-[#cfd8e3] bg-[radial-gradient(circle_at_18%_16%,rgba(37,211,102,0.06),transparent_55%),linear-gradient(#fff,#fff)] p-[24.7px] dark:border-[#1e2a3a] dark:bg-[radial-gradient(circle_at_18%_16%,rgba(37,211,102,0.08),transparent_55%),linear-gradient(#0a0f17,#0a0f17)]"
            >
              <div class="flex w-full items-center justify-end" dir="ltr">
                <template v-for="(step, index) in pipelineSteps" :key="step.key">
                  <div
                    class="flex shrink-0 items-center rounded-[10px] border border-[#25d366] bg-[#25d366]/[0.06] px-[14px] pb-[8.69px] pt-[8.5px]"
                    :class="index === pipelineSteps.length - 1 ? 'shadow-[0_0_18px_0_rgba(37,211,102,0.18)]' : ''"
                  >
                    <span class="whitespace-nowrap text-sm leading-6 text-black dark:text-white" dir="auto">{{ $t(step.key) }}</span>
                  </div>
                  <div v-if="index < pipelineSteps.length - 1" class="relative mx-[2px] h-[2px] w-[26px] shrink-0 border-t-2 border-dashed border-[#1a2332] dark:border-white">
                    <span class="absolute top-1/2 h-[5px] w-[5px] -translate-y-1/2 rounded-full bg-[#25d366] shadow-[0_0_8px_0_#25d366]" style="left: 21px"></span>
                  </div>
                </template>
              </div>
              <div class="flex w-full flex-col items-end gap-2 text-right">
                <h3 class="w-full text-[24px] font-semibold leading-[29.9px] text-black dark:text-white" dir="auto">
                  {{ $t("A bot that understands, thinks, and replies") }}
                </h3>
                <p class="w-full text-base leading-6 text-[#8899aa]" dir="auto">
                  {{ $t("An AI engine that reads customer intent in a split second, picks the best reply, and phrases it as naturally as your best employee would.") }}
                </p>
              </div>
            </div>
          </div>

          <!-- 3-card feature row -->
          <div class="flex items-center gap-4" dir="ltr">
            <div
              v-for="(card, index) in desktopFeatureCards"
              :key="card.titleKey"
              v-reveal="{ delay: index * 100 }"
              class="group flex h-[274px] w-[362.667px] shrink-0 -translate-y-0 cursor-default flex-col items-end justify-center overflow-hidden rounded-3xl bg-white p-[24.7px] transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-1.5 dark:bg-[#0a0f17]"
              :class="card.featured
                ? 'border-t-2 border-[#25d366] pt-[26px] shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_60px_0_rgba(37,211,102,0.12)] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.35),0_26px_70px_0_rgba(37,211,102,0.22)]'
                : 'border-[0.7px] border-[#cfd8e3] hover:border-[#25d366] hover:shadow-[0_18px_60px_0_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:hover:border-[#25d366]'"
            >
              <div class="flex w-full flex-col items-end gap-6">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-[#2bd46a]/[0.12] transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-110">
                  <img :src="card.icon" class="h-6 w-6" alt="" aria-hidden="true" />
                </div>
                <div class="flex w-full flex-col items-end gap-2 text-right">
                  <h3 class="w-full whitespace-nowrap text-[24px] font-semibold leading-[29.9px] text-black dark:text-white" dir="auto">
                    {{ $t(card.titleKey) }}
                  </h3>
                  <p class="w-full text-base leading-6 text-[#8899aa]" dir="auto">
                    {{ $t(card.descriptionKey) }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile: 4-card stack -->
        <div class="flex w-full flex-col items-end gap-3 lg:hidden">
          <div
            v-for="(card, index) in mobileFeatureCards"
            :key="card.titleKey"
            v-reveal="{ delay: index * 90 }"
            class="group flex h-[225px] w-full cursor-default flex-col items-end justify-center overflow-hidden rounded-2xl bg-white p-[16.7px] transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-1 dark:bg-[#0a0f17]"
            :class="card.featured
              ? 'border-t-2 border-[#25d366] pt-[18px] shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_60px_0_rgba(37,211,102,0.12)] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.35),0_26px_70px_0_rgba(37,211,102,0.22)]'
              : 'border-[0.7px] border-[#cfd8e3] hover:border-[#25d366] hover:shadow-[0_18px_60px_0_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:hover:border-[#25d366]'"
          >
            <div class="flex w-full flex-col items-end gap-4">
              <div class="flex size-12 items-center justify-center rounded-2xl bg-[#2bd46a]/[0.12] transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] group-hover:scale-110">
                <img :src="card.icon" class="h-6 w-6" alt="" aria-hidden="true" />
              </div>
              <div class="flex w-full flex-col items-end gap-2 text-right">
                <h3 class="w-full whitespace-nowrap text-lg font-semibold leading-[29.9px] text-black dark:text-white" dir="auto">
                  {{ $t(card.titleKey) }}
                </h3>
                <p class="w-full text-base leading-6 text-[#8899aa]" dir="auto">
                  {{ $t(card.descriptionKey) }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section
      id="section3"
      v-reveal
      class="premium-home-section premium-home-section--steps px-5 md:px-10 lg:px-20 2xl:px-60 lg:py-20"
    >
      <!-- Mobile - matches the provided mobile reference screenshot -->
      <div class="flex flex-col items-center gap-8 py-12 lg:hidden">
        <div class="flex w-full flex-col items-center gap-3 text-center">
          <SectionBadge :label="$t('Our process')" />
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white">
            {{ $t("Three steps, and WhatsApp runs on its own.") }}
          </h2>
          <p class="w-full text-base leading-6 text-[#8899aa]">
            {{ $t("Connect, design, launch — no code needed.") }}
          </p>
        </div>
        <HowItWorksStepLayoutMobile />
      </div>

      <!-- Desktop - 100% pixel-perfect Figma implementation -->
      <div class="hidden lg:flex lg:flex-col lg:items-center lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-4">
          <SectionBadge :label="$t('Our process')" />
          <div class="flex w-[1120px] flex-col items-center gap-4 text-center">
            <h2 class="w-full text-[46px] font-semibold leading-[56px] text-black dark:text-white">
              {{ $t("Three steps, and WhatsApp runs on its own.") }}
            </h2>
            <p class="w-full text-[24px] leading-9 text-[#8899aa]">
              {{ $t("Connect, design, launch — no code needed.") }}
            </p>
          </div>
        </div>
        <HowItWorksStepLayout />
      </div>
    </section>

    <section
      id="success-story"
      v-reveal
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-3 text-center lg:gap-4">
          <SectionBadge :label="$t('Success story')" />
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:w-[1120px] lg:text-[46px] lg:leading-[56px]">
            {{ $t("How did Assaf's Store increase sales 3x in 60 days?") }}
          </h2>
        </div>
        <SuccessStoryEvidence />
      </div>
    </section>

    <section
      id="section5"
      v-reveal
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-3 text-center lg:gap-4">
          <SectionBadge :label="$t('What clients say about Botzo')" />
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:w-[1120px] lg:text-[46px] lg:leading-[56px]">
            {{ $t("Thousands of Saudi companies trust us.") }}
          </h2>
        </div>
        <ReviewsMarquee :reviews="props.reviews" />
      </div>
    </section>

    <!-- Contact Us Section -->
    <section
      id="contact-cta"
      v-reveal
      class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20"
    >
      <div class="flex w-full flex-col items-center gap-6 overflow-hidden rounded-3xl bg-[rgba(37,211,102,0.12)] px-6 py-12 text-center md:px-10 lg:px-20 lg:py-20">
        <div class="flex w-full flex-col items-center gap-4 lg:w-[1120px] lg:gap-4">
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
            {{ $t("Contact the Botzo team") }}
          </h2>
          <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
            {{ $t("We're here to help you automate your WhatsApp communication and grow your business.") }}
          </p>
        </div>
        <a
          v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
          :href="props.companyConfig.book_a_demo_link"
          target="_blank"
          rel="noopener noreferrer"
          class="flex h-[54px] w-[220px] shrink-0 items-center justify-center rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03]"
          style="background-image: linear-gradient(135.79deg, #25d366 0%, #1db954 100%)"
        >
          <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Start the conversation') }}</span>
        </a>
      </div>
    </section>
  </FrontendLayout>
</template>
<script setup>
import { ref, computed } from "vue";
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "./FrontendLayout.vue";
import HeroChatDemo from "@/Components/HeroChatDemo.vue";
import SectionBadge from "@/Components/SectionBadge.vue";
import HowItWorksStepLayout from "@/Components/HowItWorksStepLayout.vue";
import HowItWorksStepLayoutMobile from "@/Components/HowItWorksStepLayoutMobile.vue";
import SuccessStoryEvidence from "@/Components/SuccessStoryEvidence.vue";
import ReviewsMarquee from "@/Components/ReviewsMarquee.vue";

const props = defineProps([
  "addons",
  "enable_ai_billing",
  "faqs",
  "plans",
  "reviews",
  "companyConfig",
  "languages",
  "currentLanguage",
  "currency",
  "pages",
  "premiumHomeMedia",
]);

const pipelineSteps = [
  { key: "Send" },
  { key: "Reply generation" },
  { key: "Decision making" },
  { key: "Intent analysis" },
  { key: "Incoming message" },
];

const crmCard = {
  icon: "/images/features/crm-icon.svg",
  titleKey: "Built-in CRM",
  descriptionKey: "Every conversation automatically turns into a rich customer card with smart tagging.",
  featured: false,
};
const integrationCard = {
  icon: "/images/features/integration-icon.svg",
  titleKey: "Effortless integration",
  descriptionKey: "Integrate our platform seamlessly through a simple API.",
  featured: true,
};
const campaignsCard = {
  icon: "/images/features/crm-icon.svg",
  titleKey: "Bulk campaigns",
  descriptionKey: "Send personalized messages to thousands of customers at once, fully compliant with WhatsApp policies.",
  featured: false,
};
const analyticsCard = {
  icon: "/images/features/analytics-icon.svg",
  titleKey: "Live statistics",
  descriptionKey: "Track reply rates, conversions, and peak times in a live dashboard that updates every second.",
  featured: false,
};

const desktopFeatureCards = [crmCard, integrationCard, campaignsCard];
const mobileFeatureCards = [crmCard, integrationCard, campaignsCard, analyticsCard];

const getDetail = (value, key) => {
  if (value) {
    const item = JSON.parse(value);
    return item?.[key] ?? null;
  } else {
    return null;
  }
};

const filteredAddons = (item) => {
  const addons = JSON.parse(item.metadata)["addons"];

  if (!addons || typeof addons !== "object") {
    return {}; // Return an empty object if addons is not valid
  }

  return Object.entries(props.addons).reduce((acc, [key, value]) => {
    if (value === 1 && addons.hasOwnProperty(key)) {
      acc[key] = addons[key];
    }
    return acc;
  }, {});
};

const formattedName = computed(() => {
  return (value) => {
    return value.trim().toLowerCase().replace(/\s+/g, "-");
  };
});

const resolvePremiumHomeImage = (key, fallbackPath) => {
  const configuredPath = props.premiumHomeMedia?.[key];

  if (!configuredPath || typeof configuredPath !== "string") {
    return fallbackPath;
  }

  if (
    configuredPath.startsWith("http://") ||
    configuredPath.startsWith("https://") ||
    configuredPath.startsWith("data:") ||
    configuredPath.startsWith("/")
  ) {
    return configuredPath;
  }

  return `/media/${configuredPath}`;
};

const user6Image = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_avatar_top_right",
    "/images/hero/user-6.png",
  ),
);
const user3Image = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_avatar_top_left",
    "/images/hero/user-3.png",
  ),
);
const user4Image = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_avatar_bottom_left",
    "/images/hero/user-4.png",
  ),
);
const user2Image = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_avatar_bottom_right",
    "/images/hero/user-2.png",
  ),
);
const dashboard2Image = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_dashboard",
    "/images/hero/dashboard2.png",
  ),
);
const heroBackgroundImage = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_background",
    "/images/hero/hero-background-default.svg",
  ),
);
const heroSectionStyle = computed(() => ({
  backgroundImage: `url(${heroBackgroundImage.value})`,
  backgroundPosition: "center top",
  backgroundSize: "cover",
  backgroundRepeat: "no-repeat",
}));
</script>
