<template>
  <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
    <section
      id="section1"
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-32 py-8 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="pointer-events-none absolute inset-0 hero-fade" aria-hidden="true"></div>

      <div
        class="pointer-events-none absolute -top-20 end-0 h-[520px] w-[520px] corner-glow dark:h-[624px] dark:w-[624px]"
        aria-hidden="true"
      ></div>
      <div
        class="pointer-events-none absolute bottom-0 start-0 h-[420px] w-[420px] corner-glow dark:h-[500px] dark:w-[500px]"
        aria-hidden="true"
      ></div>

      <div class="pointer-events-none absolute inset-0 hidden overflow-hidden lg:block" aria-hidden="true">
        <HeroNetworkScene />

        <!-- WhatsApp Business-style notification toasts filling the empty
             side margins — each pops in, holds, fades out, then cycles to
             its next message and repeats, staggered so they never sync up. -->
        <HeroNotificationToast
          class="absolute start-2 top-[130px]"
          :items="heroToastMessagesA"
          :delay="0"
          :duration="5200"
        />
        <HeroNotificationToast
          class="absolute end-2 top-[230px]"
          :items="heroToastMessagesB"
          :delay="1300"
          :duration="5600"
        />
        <HeroNotificationToast
          class="absolute start-1 top-[480px]"
          :items="heroToastMessagesC"
          :delay="2600"
          :duration="5000"
        />
        <HeroNotificationToast
          class="absolute end-1 top-[570px]"
          :items="heroToastMessagesD"
          :delay="900"
          :duration="5400"
        />

        <!-- Real WhatsApp badges floating alongside the network, distinct
             from the notification toasts above. -->
        <img
          src="/images/footer/whatsapp-icon.svg"
          alt=""
          class="hero-whatsapp-icon absolute start-24 top-[360px] h-12 w-12 drop-shadow-[0_10px_22px_rgba(37,211,102,0.35)]"
          style="animation-delay: 0s"
        />
        <img
          src="/images/footer/whatsapp-icon.svg"
          alt=""
          class="hero-whatsapp-icon absolute end-32 top-[420px] h-9 w-9 drop-shadow-[0_10px_22px_rgba(37,211,102,0.35)]"
          style="animation-delay: 1.4s"
        />
        <img
          src="/images/footer/whatsapp-icon.svg"
          alt=""
          class="hero-whatsapp-icon absolute start-16 top-[500px] h-8 w-8 drop-shadow-[0_10px_22px_rgba(37,211,102,0.35)]"
          style="animation-delay: 0.7s"
        />
      </div>

      <div class="relative z-10 flex flex-col items-center justify-center gap-12 lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-4 lg:w-[598px] lg:gap-6">
          <div class="flex w-full flex-col items-center gap-2 text-center lg:gap-4">
            <h1 class="font-semibold text-[24px] leading-[29.9px] text-black dark:text-white lg:text-[64px] lg:leading-[1.4]">
              {{ $t("WhatsApp works even while you sleep.") }}
            </h1>
            <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
              {{ $t("Turn WhatsApp into a smart sales agent that replies, follows up, and closes deals around the clock.") }}
            </p>
          </div>

          <button
            type="button"
            class="flex h-[54px] w-[246px] shrink-0 items-center justify-center gap-2 rounded-2xl bg-[#25d366] px-8 transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:scale-[1.04] hover:shadow-[0_12px_30px_-8px_rgba(37,211,102,0.55)] active:scale-[0.98]"
            @click="showBookDemoModal = true"
          >
            <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t("Book a demo") }}</span>
            <img src="/images/hero/arrow.svg" class="h-4 w-4 shrink-0 rotate-180 rtl:rotate-0" alt="" aria-hidden="true" />
          </button>
        </div>

        <HeroChatDemo />
      </div>
    </section>

    <BookDemoModal :is-open="showBookDemoModal" @close="showBookDemoModal = false" />

    <section
      id="section2"
      v-reveal
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-32 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
        <!-- Header -->
        <div class="flex w-full flex-col items-center gap-3 lg:gap-4">
          <SectionBadge :label="$t('Features')" />
          <div class="flex w-full flex-col items-center gap-2 text-center lg:w-[1280px] lg:gap-4">
            <h2 class="font-semibold text-[22px] leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
              {{ $t("Everything you need to turn WhatsApp into a sales machine") }}
            </h2>
            <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
              {{ $t("Features built to double your sales and cut your work hours.") }}
            </p>
          </div>
        </div>

        <!-- Desktop/tablet: KPI + bot-workflow row, then 3 feature cards -->
        <div class="hidden flex-col items-end gap-6 lg:flex lg:w-[1280px]">
          <div class="flex items-center gap-4" dir="ltr">
            <!-- KPI card -->
            <div
              v-tilt
              class="tilt-sheen relative flex h-[408px] w-[416px] shrink-0 flex-col items-end justify-center gap-6 overflow-hidden rounded-3xl border-[0.7px] border-[#e4ece7] bg-[var(--surface-1)] p-[24.7px] dark:border-[#1e2a3a] dark:bg-[#0b161a]"
            >
              <div class="flex h-[120.8px] w-full items-end justify-center gap-[9px] pt-[0.8px]">
                <div class="botzo-bar h-[110.39px] flex-1 rounded-t-[6px] bg-[#25d366]" style="--d: 0s"></div>
                <div class="botzo-bar h-[79.19px] flex-1 rounded-t-[6px] bg-[#25d366]" style="--d: 0.09s"></div>
                <div class="botzo-bar h-[88.8px] flex-1 rounded-t-[6px] bg-[#25d366]" style="--d: 0.18s"></div>
                <div class="botzo-bar h-[60px] flex-1 rounded-t-[6px] bg-[#25d366]" style="--d: 0.27s"></div>
                <div class="botzo-bar h-[69.59px] flex-1 rounded-t-[6px] bg-[#25d366]" style="--d: 0.36s"></div>
                <div class="botzo-bar h-[50.39px] flex-1 rounded-t-[6px] bg-[#25d366]" style="--d: 0.45s"></div>
              </div>
              <div class="flex w-full flex-col items-end gap-3">
                <p
                  class="w-full whitespace-nowrap bg-clip-text text-right text-[64px] font-semibold leading-[1.4] text-transparent"
                  style="background-image: linear-gradient(103deg, #25d366 0%, #3b82f6 100%)"
                  dir="auto"
                >
                  ↑ <span class="tabular-nums" data-count="31">{{ statCountDisplay }}</span>
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
              v-tilt
              class="tilt-sheen tilt-sheen--wide relative flex h-[408px] w-[848px] shrink-0 flex-col items-end gap-6 overflow-hidden rounded-3xl border-[0.7px] border-[#e4ece7] bg-[radial-gradient(circle_at_18%_16%,rgba(37,211,102,0.06),transparent_55%),linear-gradient(var(--surface-1),var(--surface-1))] p-[24.7px] dark:border-[#1e2a3a] dark:bg-[radial-gradient(circle_at_18%_16%,rgba(37,211,102,0.08),transparent_55%),linear-gradient(#0d1f14,#0d1f14)]"
            >
              <div class="flex w-full items-center justify-end" dir="ltr">
                <template v-for="(step, index) in pipelineSteps" :key="step.key">
                  <div
                    :data-chip="index"
                    class="flex shrink-0 items-center gap-[6px] rounded-[10px] border px-[14px] pb-[8.69px] pt-[8.5px] transition-colors duration-[350ms]"
                    :class="index === activeChipIndex
                      ? 'border-[#25d366] bg-[#25d366] shadow-[0_0_18px_0_rgba(37,211,102,0.35)]'
                      : 'border-[#cfd8e3] bg-white dark:border-[#1e2a3a] dark:bg-[#25d366]/[0.06]'"
                  >
                    <component
                      :is="step.icon"
                      :size="14"
                      class="shrink-0"
                      :class="[
                        index === activeChipIndex ? [step.activeAnim, 'text-[#04130a]'] : 'text-[#445566] dark:text-[#8899aa]',
                      ]"
                    />
                    <span
                      class="whitespace-nowrap text-sm leading-6"
                      :class="index === activeChipIndex ? 'font-semibold text-[#04130a]' : 'text-[#445566] dark:text-white'"
                      dir="auto"
                    >{{ $t(step.key) }}</span>
                  </div>
                  <div
                    v-if="index < pipelineSteps.length - 1"
                    data-flowline
                    class="botzo-draw relative mx-[2px] h-[2px] w-[26px] shrink-0 border-t-2 border-dashed border-[#1a2332] dark:border-white"
                    :style="{ '--d': `${index * 0.35}s` }"
                  >
                    <span class="botzo-dot absolute top-1/2 h-[5px] w-[5px] -translate-y-1/2 rounded-full bg-[#25d366] shadow-[0_0_8px_0_#25d366]" style="left: 21px"></span>
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

              <!-- Mini chat demo — plays out in sync with the chips above -->
              <div class="mt-2 flex w-full flex-1 flex-col justify-end gap-3 px-1 pb-1" dir="rtl">
                <div class="flex justify-start">
                  <div class="max-w-[70%] rounded-2xl rounded-ss-sm border border-[#dbe4e0] bg-[#eef2f0] px-4 py-2.5 shadow-[0_2px_10px_rgba(15,23,42,0.05)] dark:border-white/[0.08] dark:bg-[#22304a] dark:shadow-none">
                    <p class="text-sm leading-6 text-black dark:text-white" dir="auto">{{ $t("When will my order arrive?") }}</p>
                  </div>
                </div>
                <div class="flex h-9 justify-end">
                  <div
                    class="flex items-center gap-1.5 rounded-2xl rounded-ee-sm bg-[#25d366]/[0.14] px-4 py-2.5 transition-all duration-300"
                    :class="botDemoPhase === 'thinking' ? 'translate-y-0 scale-100 opacity-100' : 'translate-y-1 scale-95 opacity-0'"
                  >
                    <span class="bot-demo-dot h-[6px] w-[6px] rounded-full bg-[#25d366]" style="--d: 0s"></span>
                    <span class="bot-demo-dot h-[6px] w-[6px] rounded-full bg-[#25d366]" style="--d: 0.15s"></span>
                    <span class="bot-demo-dot h-[6px] w-[6px] rounded-full bg-[#25d366]" style="--d: 0.3s"></span>
                  </div>
                </div>
                <div class="flex justify-end">
                  <div
                    class="max-w-[70%] rounded-2xl rounded-ee-sm bg-[#25d366] px-4 py-2.5 shadow-[0_6px_20px_-4px_rgba(37,211,102,0.45)] transition-all duration-500"
                    :class="botDemoPhase === 'replied' ? 'translate-y-0 scale-100 opacity-100' : 'pointer-events-none translate-y-1.5 scale-95 opacity-0'"
                  >
                    <p class="text-sm leading-6 text-[#04130a]" dir="auto">{{ $t("It'll arrive within 24 hours, God willing ✅") }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- 3-card feature row -->
          <div class="flex items-center gap-4" dir="ltr">
            <div
              v-for="(card, index) in desktopFeatureCards"
              :key="card.titleKey"
              v-reveal="{ delay: index * 100 }"
              v-tilt
              class="tilt-sheen group relative flex h-[274px] w-[416px] shrink-0 -translate-y-0 cursor-default flex-col items-end justify-center overflow-hidden rounded-3xl bg-[var(--surface-1)] p-[24.7px] transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-1.5 dark:bg-[#0b161a]"
              :class="card.featured
                ? 'border-t-2 border-[#25d366] pt-[26px] shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_60px_0_rgba(37,211,102,0.12)] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.35),0_26px_70px_0_rgba(37,211,102,0.22)]'
                : 'border-[0.7px] border-[#e4ece7] hover:border-[#25d366] hover:shadow-[0_18px_60px_0_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:hover:border-[#25d366]'"
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
            v-tilt
            class="tilt-sheen group relative flex h-[225px] w-full cursor-default flex-col items-end justify-center overflow-hidden rounded-2xl bg-[var(--surface-1)] p-[16.7px] transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] hover:-translate-y-1 dark:bg-[#0b161a]"
            :class="card.featured
              ? 'border-t-2 border-[#25d366] pt-[18px] shadow-[0_0_0_1px_rgba(37,211,102,0.2),0_18px_60px_0_rgba(37,211,102,0.12)] hover:shadow-[0_0_0_1px_rgba(37,211,102,0.35),0_26px_70px_0_rgba(37,211,102,0.22)]'
              : 'border-[0.7px] border-[#e4ece7] hover:border-[#25d366] hover:shadow-[0_18px_60px_0_rgba(37,211,102,0.12)] dark:border-[#1e2a3a] dark:hover:border-[#25d366]'"
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
      class="premium-home-section premium-home-section--steps px-5 md:px-10 lg:px-20 2xl:px-32 lg:py-20"
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
          <div class="flex w-[1280px] flex-col items-center gap-4 text-center">
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
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-32 py-12 md:py-16 lg:py-20 bg-[var(--surface-anchor)] dark:bg-[#0f5c2e]"
    >
      <div
        class="pointer-events-none absolute -top-20 end-0 h-[520px] w-[520px] corner-glow dark:h-[624px] dark:w-[624px]"
        aria-hidden="true"
      ></div>

      <div class="relative flex flex-col items-center gap-8 lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-3 text-center lg:gap-4">
          <!-- This band is always the dark "anchor" ground (in both themes), so
               the badge is forced onto its own dark: styling regardless of the
               site's real theme — otherwise its light-mode glass look (meant
               for a light ground) washes out here. -->
          <div class="dark">
            <SectionBadge :label="$t('Success story')" />
          </div>
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-[#e9eef5] dark:text-white lg:w-[1280px] lg:text-[46px] lg:leading-[56px]">
            {{ $t("How did Nora increase her store's sales 3x in 60 days?") }}
          </h2>
        </div>
        <SuccessStoryEvidence />
      </div>
    </section>

    <section
      id="section5"
      v-reveal
      class="relative overflow-hidden px-5 md:px-10 lg:px-20 2xl:px-32 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-3 text-center lg:gap-4">
          <div class="flex w-full max-w-[420px] items-center justify-center gap-4">
            <span class="hidden h-px flex-1 bg-[var(--line-strong)] dark:hidden sm:block"></span>
            <SectionBadge :label="$t('What clients say about Botzo')" />
            <span class="hidden h-px flex-1 bg-[var(--line-strong)] dark:hidden sm:block"></span>
          </div>
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:w-[1280px] lg:text-[46px] lg:leading-[56px]">
            {{ $t("Thousands of Saudi companies trust us.") }}
          </h2>
        </div>
        <ReviewsMarquee :reviews="props.reviews" />
      </div>
    </section>

    <PricingSection :plans="props.plans" :addons="props.addons" :currency="props.currency">
      <template #banner>
        <!-- Meta Business Verification — kept as a compact white banner inside the
             pricing section's ground rather than its own near-empty section. -->
        <div
          id="meta-verification"
          v-reveal
          class="flex w-full flex-col items-center gap-6 overflow-hidden rounded-3xl bg-white px-6 py-12 text-center dark:bg-[rgba(37,211,102,0.12)] md:px-10 lg:w-[1280px] lg:px-20 lg:py-20"
        >
          <div class="flex w-full flex-col items-center gap-4 lg:w-[1280px] lg:gap-4">
            <SectionBadge :label="$t('Independent Service')" />
            <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
              {{ $t("Meta business account verification") }}
            </h2>
            <p class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
              {{ $t("We prepare and follow up your Meta business verification request professionally") }}
            </p>
          </div>
          <Link
            href="/meta-verification"
            class="flex h-[54px] w-[185px] shrink-0 items-center justify-center gap-2 rounded-2xl px-8 transition-transform duration-200 hover:scale-[1.03]"
            style="background-image: linear-gradient(131deg, #25d366 0%, #1db954 100%)"
          >
            <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#04130a]">{{ $t('Learn More') }}</span>
            <img src="/images/hero/arrow.svg" class="h-4 w-4 shrink-0 rotate-180 rtl:rotate-0" alt="" aria-hidden="true" />
          </Link>
        </div>
      </template>
    </PricingSection>

    <!-- FAQs Section -->
    <section
      v-if="homeFaqs.length > 0"
      id="faqs"
      v-reveal
      class="px-5 md:px-10 lg:px-20 2xl:px-32 py-12 md:py-16 lg:py-20 bg-[var(--surface-1)] dark:bg-[#0a0f17]"
    >
      <div class="flex flex-col items-center gap-8 lg:gap-[72px]">
        <div class="flex w-full flex-col items-center gap-3 text-center lg:gap-4">
          <SectionBadge :label="$t('FAQs')" />
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-black dark:text-white lg:w-[1280px] lg:text-[46px] lg:leading-[56px]">
            {{ $t('Everything you want to know') }}
          </h2>
        </div>
        <div class="flex w-full flex-col items-center gap-6 lg:w-[760px]">
          <FaqAccordion :items="homeFaqs" />
          <Link
            v-if="hasMoreFaqs"
            href="/faqs"
            class="flex h-12 shrink-0 items-center justify-center rounded-2xl border border-[#1a2332] px-8 transition-colors hover:border-[#25d366] dark:border-white"
          >
            <span class="whitespace-nowrap text-sm font-semibold text-black dark:text-white">{{ $t('View all FAQs') }}</span>
          </Link>
        </div>
      </div>
    </section>

    <!-- Contact Us Section -->
    <section
      id="contact-cta"
      v-reveal
    >
      <div class="flex w-full flex-col items-center gap-6 bg-[var(--accent-solid)] px-6 py-12 text-center dark:bg-[rgba(37,211,102,0.12)] md:px-10 lg:px-20 lg:py-20">
        <div class="flex w-full flex-col items-center gap-4 lg:w-[1280px] lg:gap-4">
          <h2 class="w-full text-[22px] font-semibold leading-[29.9px] text-white dark:text-white lg:text-[46px] lg:leading-[56px]">
            {{ $t("Contact the Botzo team") }}
          </h2>
          <p class="w-full text-base leading-6 text-white dark:text-[#8899aa] lg:text-[24px] lg:leading-9">
            {{ $t("We're here to help you automate your WhatsApp communication and grow your business.") }}
          </p>
        </div>
        <a
          v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
          :href="props.companyConfig.book_a_demo_link"
          target="_blank"
          rel="noopener noreferrer"
          class="flex h-[54px] w-[220px] shrink-0 items-center justify-center rounded-2xl bg-white px-8 transition-transform duration-200 hover:scale-[1.03] dark:bg-gradient-to-br dark:from-[#25d366] dark:to-[#1db954]"
        >
          <span class="whitespace-nowrap text-base font-semibold leading-5 text-[#0a6433] dark:text-[#04130a]">{{ $t('Start the conversation') }}</span>
        </a>
      </div>
    </section>
  </FrontendLayout>
</template>
<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Link } from "@inertiajs/vue3";
import { MessageCircle, ScanLine, GitBranch, Sparkles, Send as SendIcon } from "lucide-vue-next";
import FrontendLayout from "./FrontendLayout.vue";
import HeroChatDemo from "@/Components/HeroChatDemo.vue";
import HeroNetworkScene from "@/Components/HeroNetworkScene.vue";
import HeroNotificationToast from "@/Components/HeroNotificationToast.vue";
import SectionBadge from "@/Components/SectionBadge.vue";
import HowItWorksStepLayout from "@/Components/HowItWorksStepLayout.vue";
import HowItWorksStepLayoutMobile from "@/Components/HowItWorksStepLayoutMobile.vue";
import SuccessStoryEvidence from "@/Components/SuccessStoryEvidence.vue";
import ReviewsMarquee from "@/Components/ReviewsMarquee.vue";
import PricingSection from "@/Components/PricingSection.vue";
import BookDemoModal from "@/Components/BookDemoModal.vue";
import FaqAccordion from "@/Components/FaqAccordion.vue";

const showBookDemoModal = ref(false);

// Content each hero notification toast cycles through — static copy, not
// translated, since these are decorative WhatsApp-message mockups rather
// than real UI strings.
const heroToastMessagesA = [
  { name: "سارة العتيبي", message: "متاح توصيل الطلب النهارده؟", time: "الآن", color: "#25d366" },
  { name: "سارة العتيبي", message: "تمام، هطلب حالاً 👍", time: "2 د", color: "#25d366" },
];
const heroToastMessagesB = [
  { name: "Botzo", message: "تم الرد التلقائي على 12 عميل", time: "الآن", color: "#0d83da" },
  { name: "Botzo", message: "جاري تجهيز عرض السعر...", time: "1 د", color: "#0d83da" },
];
const heroToastMessagesC = [
  { name: "خالد المطيري", message: "شكراً جزيلاً على السرعة!", time: "3 د", color: "#f59e0b" },
  { name: "خالد المطيري", message: "الطلب وصل، تقييمي 5 نجوم ⭐", time: "5 د", color: "#f59e0b" },
];
const heroToastMessagesD = [
  { name: "نورة القحطاني", message: "ممكن أعرف مواعيد التوصيل؟", time: "الآن", color: "#8b5cf6" },
  { name: "نورة القحطاني", message: "تم تأكيد الطلب ✅", time: "2 د", color: "#8b5cf6" },
];

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
  { key: "Send", icon: SendIcon, activeAnim: "chip-anim-send" },
  { key: "Reply generation", icon: Sparkles, activeAnim: "chip-anim-generate" },
  { key: "Decision making", icon: GitBranch, activeAnim: "chip-anim-decide" },
  { key: "Intent analysis", icon: ScanLine, activeAnim: "chip-anim-scan" },
  { key: "Incoming message", icon: MessageCircle, activeAnim: "chip-anim-ping" },
];

// Features-section motion: the active pipeline chip cycles on its own timer,
// independent of the connector-line/dot CSS animations (both pure CSS, no
// JS needed there). Everything here is torn down on unmount and respects
// prefers-reduced-motion.
const prefersReducedMotionQuery = typeof window !== "undefined" ? window.matchMedia("(prefers-reduced-motion: reduce)") : null;
const prefersReducedMotion = () => prefersReducedMotionQuery?.matches ?? false;

const activeChipIndex = ref(4);
let chipCycleInterval = null;

// Fills the bot-workflow card's empty lower half with a tiny chat demo that
// mirrors whichever pipeline step is currently highlighted, instead of a
// second unrelated animation: chip 4 ("Incoming message") = a fresh customer
// message just arrived, chips 3/2/1 (analysis/decision/generation) = the bot
// "thinking", chip 0 ("Send") = the reply has gone out.
const botDemoPhase = computed(() => {
  if (activeChipIndex.value === 4) return "received";
  if (activeChipIndex.value === 0) return "replied";
  return "thinking";
});

const STAT_TARGET = 31;
const STAT_COUNT_DURATION = 1500;
const STAT_COUNT_REPEAT = 7000;
const arabicIndicDigits = ["٠", "١", "٢", "٣", "٤", "٥", "٦", "٧", "٨", "٩"];
const isArabicLocale = () => typeof document !== "undefined" && document.documentElement.lang === "ar";
const formatStatValue = (value) => {
  const rounded = Math.round(value);
  if (isArabicLocale()) {
    return String(rounded).replace(/[0-9]/g, (digit) => arabicIndicDigits[digit]) + "٪";
  }
  return `${rounded}%`;
};
const statCountDisplay = ref(formatStatValue(STAT_TARGET));
let statCountRAF = null;
let statCountInterval = null;

function runStatCount() {
  const start = performance.now();
  const tick = (now) => {
    const progress = Math.min((now - start) / STAT_COUNT_DURATION, 1);
    const eased = 1 - Math.pow(1 - progress, 3);
    statCountDisplay.value = formatStatValue(eased * STAT_TARGET);
    if (progress < 1) {
      statCountRAF = requestAnimationFrame(tick);
    }
  };
  statCountRAF = requestAnimationFrame(tick);
}

onMounted(() => {
  if (prefersReducedMotion()) {
    statCountDisplay.value = formatStatValue(STAT_TARGET);
    activeChipIndex.value = 4;
    return;
  }

  runStatCount();
  statCountInterval = setInterval(runStatCount, STAT_COUNT_REPEAT);

  // Counts DOWN (4→3→2→1→0), matching the natural process order rendered
  // right-to-left in this dir="ltr" row: Incoming message (4) is rightmost/
  // first, Send (0) is leftmost/last.
  chipCycleInterval = setInterval(() => {
    activeChipIndex.value = (activeChipIndex.value - 1 + pipelineSteps.length) % pipelineSteps.length;
  }, 3000);
});

onUnmounted(() => {
  if (statCountRAF) cancelAnimationFrame(statCountRAF);
  if (statCountInterval) clearInterval(statCountInterval);
  if (chipCycleInterval) clearInterval(chipCycleInterval);
});

// Pointer-tilt directive for the Features-section cards — perspective tilt
// on move, a radial sheen following the pointer (driven by --mx/--my/--sheen
// custom properties consumed by CSS in the scoped <style> below), and a
// smooth return to flat on leave. Skipped entirely under reduced motion.
const MAX_TILT_DEG = 5;
const vTilt = {
  mounted(el) {
    if (prefersReducedMotion()) return;

    const handleMove = (event) => {
      const rect = el.getBoundingClientRect();
      const x = (event.clientX - rect.left) / rect.width;
      const y = (event.clientY - rect.top) / rect.height;
      el.classList.add("tilt-active");
      el.style.setProperty("--mx", `${x * 100}%`);
      el.style.setProperty("--my", `${y * 100}%`);
      el.style.setProperty("--sheen", "1");
      el.style.transform = `perspective(900px) rotateX(${(0.5 - y) * MAX_TILT_DEG}deg) rotateY(${(x - 0.5) * MAX_TILT_DEG}deg)`;
    };
    const handleLeave = () => {
      el.classList.remove("tilt-active");
      el.style.setProperty("--sheen", "0");
      el.style.transform = "perspective(900px) rotateX(0deg) rotateY(0deg)";
    };

    el.addEventListener("pointermove", handleMove);
    el.addEventListener("pointerleave", handleLeave);
    el.__tiltCleanup = () => {
      el.removeEventListener("pointermove", handleMove);
      el.removeEventListener("pointerleave", handleLeave);
    };
  },
  unmounted(el) {
    el.__tiltCleanup?.();
  },
};

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

const homeFaqs = computed(() => (props.faqs?.data ?? []).slice(0, 6));
const hasMoreFaqs = computed(() => (props.faqs?.data?.length ?? 0) > 6);

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

<style scoped>
.hero-whatsapp-icon {
  animation: hero-whatsapp-float 4.5s ease-in-out infinite;
}

@keyframes hero-whatsapp-float {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .hero-whatsapp-icon {
    animation: none;
  }
}

/* Features section motion (id="section2") — bars, connector lines/dot, and
   pointer-tilt sheen. Only transform/opacity/background/border-color are
   animated, all colors come from existing tokens/hex already used in this
   section, and everything rests in its final state under reduced motion. */
.botzo-bar {
  transform-origin: bottom;
  animation: botzo-bar 7s cubic-bezier(0.22, 1, 0.36, 1) var(--d, 0s) infinite;
}

@keyframes botzo-bar {
  0%,
  6% {
    transform: scaleY(0.14);
  }
  30%,
  86% {
    transform: scaleY(1);
  }
  100% {
    transform: scaleY(0.14);
  }
}

.botzo-draw {
  transform-origin: right;
  animation: botzo-draw 3s ease-in-out var(--d, 0s) infinite;
}

@keyframes botzo-draw {
  0% {
    transform: scaleX(0);
  }
  45%,
  100% {
    transform: scaleX(1);
  }
}

.botzo-dot {
  animation: botzo-dot 2.4s ease-in-out infinite;
}

@keyframes botzo-dot {
  0%,
  100% {
    transform: scale(1) translateY(-50%);
    opacity: 0.5;
  }
  50% {
    transform: scale(1.5) translateY(-50%);
    opacity: 1;
  }
}

.tilt-sheen {
  transition: transform 0.6s cubic-bezier(0.22, 1, 0.36, 1);
}

.tilt-sheen.tilt-active {
  transition: transform 0.12s linear;
}

.tilt-sheen::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: inherit;
  pointer-events: none;
  background: radial-gradient(220px circle at var(--mx, 50%) var(--my, 0%), rgba(37, 211, 102, 0.12), transparent 65%);
  opacity: var(--sheen, 0);
  transition: opacity 0.35s;
}

.tilt-sheen--wide::after {
  background: radial-gradient(260px circle at var(--mx, 50%) var(--my, 0%), rgba(37, 211, 102, 0.12), transparent 65%);
}

.bot-demo-dot {
  animation: bot-demo-bounce 1.2s ease-in-out var(--d, 0s) infinite;
}

@keyframes bot-demo-bounce {
  0%,
  60%,
  100% {
    transform: translateY(0);
    opacity: 0.5;
  }
  30% {
    transform: translateY(-3px);
    opacity: 1;
  }
}

/* Each pipeline chip gets its own signature motion while active, instead of
   every step reusing the same generic pulse. */
.chip-anim-ping {
  animation: chip-anim-ping 1s ease-in-out infinite;
}

@keyframes chip-anim-ping {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.35);
  }
}

.chip-anim-scan {
  animation: chip-anim-scan 1.4s ease-in-out infinite;
}

@keyframes chip-anim-scan {
  0%,
  100% {
    transform: translateX(0);
  }
  50% {
    transform: translateX(-3px);
  }
}

.chip-anim-decide {
  animation: chip-anim-decide 1.1s ease-in-out infinite;
}

@keyframes chip-anim-decide {
  0%,
  100% {
    transform: rotate(0deg);
  }
  50% {
    transform: rotate(18deg);
  }
}

.chip-anim-generate {
  animation: chip-anim-generate 0.9s ease-in-out infinite;
}

@keyframes chip-anim-generate {
  0%,
  100% {
    transform: scale(1) rotate(0deg);
    opacity: 1;
  }
  50% {
    transform: scale(1.25) rotate(-12deg);
    opacity: 0.65;
  }
}

.chip-anim-send {
  animation: chip-anim-send 1s ease-in-out infinite;
}

@keyframes chip-anim-send {
  0%,
  100% {
    transform: translateX(0);
    opacity: 1;
  }
  50% {
    transform: translateX(3px);
    opacity: 0.6;
  }
}

@media (prefers-reduced-motion: reduce) {
  .botzo-bar,
  .botzo-draw,
  .botzo-dot,
  .bot-demo-dot,
  .chip-anim-ping,
  .chip-anim-scan,
  .chip-anim-decide,
  .chip-anim-generate,
  .chip-anim-send {
    animation: none !important;
  }
  .tilt-sheen {
    transition: none !important;
  }
}
</style>
