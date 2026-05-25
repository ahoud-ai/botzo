<template>
  <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
    <section
      id="section1"
      class="premium-home-section premium-home-section--hero premium-home-hero-v2 px-5 md:px-10 lg:px-20 2xl:px-60 relative overflow-hidden"
    >
      <div class="premium-home-hero-wave" aria-hidden="true"></div>
      <div class="premium-home-hero-v2-grid">
        <div class="premium-home-hero-copy premium-home-animate">
          <div class="premium-home-hero-pill">
            <span class="premium-home-hero-pill__whatsapp">WhatsApp</span>
            <span class="premium-home-hero-pill__meta">Meta</span>
            <span>{{ $t("Integrated WhatsApp and Meta solutions") }}</span>
          </div>

          <h1 class="premium-home-hero-title premium-home-hero-title--v2">
            {{ $t("Smart WhatsApp and Meta solutions") }} <span>{{ $t("that grow your business") }}</span>
          </h1>

          <p class="premium-home-hero-subtitle premium-home-hero-subtitle--v2">
            {{ $t("We give you the tools and expertise to automate customer conversations across") }}
            <span>WhatsApp</span>
            {{ $t("and") }}
            <span>Meta</span>
            {{ $t("platforms to improve customer experience and increase sales.") }}
          </p>

          <div class="premium-home-hero-actions">
            <Link href="/signup" class="premium-home-hero-primary-action premium-home-hero-cta">
              <span>{{ $t("Start now") }}</span>
              <svg class="h-5 w-5 rtl:rotate-180" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </Link>
            <a
              v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
              :href="props.companyConfig.book_a_demo_link"
              target="_blank"
              rel="noopener noreferrer"
              class="premium-home-hero-secondary-action premium-home-hero-cta premium-home-hero-cta--secondary"
            >
              <span>{{ $t("Book a demo") }}</span>
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M8 2v4m8-4v4M3.5 9.5h17M6 4h12a2.5 2.5 0 0 1 2.5 2.5v11A2.5 2.5 0 0 1 18 20H6a2.5 2.5 0 0 1-2.5-2.5v-11A2.5 2.5 0 0 1 6 4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </a>
            <Link
              v-else
              href="/contact"
              class="premium-home-hero-secondary-action premium-home-hero-cta premium-home-hero-cta--secondary"
            >
              <span>{{ $t("Book a demo") }}</span>
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M8 2v4m8-4v4M3.5 9.5h17M6 4h12a2.5 2.5 0 0 1 2.5 2.5v11A2.5 2.5 0 0 1 18 20H6a2.5 2.5 0 0 1-2.5-2.5v-11A2.5 2.5 0 0 1 6 4Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </Link>
          </div>
        </div>

        <div class="premium-home-hero-visual premium-home-animate">
          <div class="premium-home-hero-channel-stack" aria-hidden="true">
            <span class="premium-home-channel premium-home-channel--whatsapp">WA</span>
            <span class="premium-home-channel premium-home-channel--meta">Meta</span>
            <span class="premium-home-channel premium-home-channel--chat">AI</span>
          </div>

          <div class="premium-home-dashboard-card">
            <div class="premium-home-dashboard-top">
              <span>{{ $t("Dashboard") }}</span>
              <span>Botzo Admin</span>
            </div>
            <div class="premium-home-dashboard-stats">
              <div>
                <span>{{ $t("Conversations") }}</span>
                <strong>128K</strong>
                <small>+12.5%</small>
              </div>
              <div>
                <span>{{ $t("New customers") }}</span>
                <strong>24K</strong>
                <small>+15.3%</small>
              </div>
              <div>
                <span>{{ $t("Response rate") }}</span>
                <strong>98%</strong>
                <small>+8.6%</small>
              </div>
              <div>
                <span>{{ $t("Messages") }}</span>
                <strong>2.5M</strong>
                <small>+18.7%</small>
              </div>
            </div>
            <div class="premium-home-dashboard-body">
              <div class="premium-home-chart-card">
                <div class="premium-home-chart-head">
                  <span>{{ $t("Conversation performance") }}</span>
                  <small>{{ $t("Last 7 days") }}</small>
                </div>
                <svg viewBox="0 0 420 170" preserveAspectRatio="none" aria-hidden="true">
                  <defs>
                    <linearGradient id="heroChartFill" x1="0" x2="0" y1="0" y2="1">
                      <stop offset="0%" stop-color="#2f7bff" stop-opacity="0.28" />
                      <stop offset="100%" stop-color="#2f7bff" stop-opacity="0" />
                    </linearGradient>
                  </defs>
                  <path d="M0 136 C40 118 50 95 86 105 C126 120 132 68 172 82 C213 98 218 38 258 56 C302 77 308 28 350 45 C382 58 394 24 420 30 L420 170 L0 170 Z" fill="url(#heroChartFill)" />
                  <path d="M0 136 C40 118 50 95 86 105 C126 120 132 68 172 82 C213 98 218 38 258 56 C302 77 308 28 350 45 C382 58 394 24 420 30" fill="none" stroke="#2f7bff" stroke-width="4" stroke-linecap="round" />
                </svg>
              </div>
              <div class="premium-home-channel-list">
                <span>{{ $t("Connected channels") }}</span>
                <div>WhatsApp</div>
                <div>Instagram</div>
                <div>Messenger</div>
                <div>Facebook</div>
              </div>
            </div>
          </div>

          <div class="premium-home-phone-card">
            <div class="premium-home-phone-top">9:41</div>
            <div class="premium-home-phone-profile">
              <span></span>
              <div>
                <strong>{{ $t("Elegance Store") }}</strong>
                <small>{{ $t("Online now") }}</small>
              </div>
            </div>
            <div class="premium-home-chat-bubble premium-home-chat-bubble--in">{{ $t("Hello, how can I help you?") }}</div>
            <div class="premium-home-chat-bubble premium-home-chat-bubble--out">{{ $t("I want to ask about the product price") }}</div>
            <div class="premium-home-product-preview">
              <div></div>
              <strong>{{ $t("SAR 2,999") }}</strong>
              <span>{{ $t("View product") }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="premium-home-hero-feature-row">
        <article v-for="feature in heroFeatureCards" :key="feature.title" class="premium-home-hero-feature-card">
          <div class="premium-home-hero-feature-icon" v-html="feature.icon"></div>
          <h3>{{ $t(feature.title) }}</h3>
          <p>{{ $t(feature.description) }}</p>
        </article>
      </div>

      <div v-if="showHeroPartners" class="premium-home-hero-partners" aria-disabled="true">
        <p>{{ $t("Trusted by more than 5,000 companies around the world") }}</p>
      </div>
    </section>
    <section
      id="section2"
      class="premium-home-section premium-home-section--features mt-0 px-5 md:px-10 lg:px-20 py-20 2xl:px-60"
    >
      <div class="premium-home-orbit-layer" aria-hidden="true">
        <span class="premium-home-orbit premium-home-orbit--a"></span>
        <span class="premium-home-orbit premium-home-orbit--b"></span>
        <span class="premium-home-orbit premium-home-orbit--c"></span>
      </div>
      <div class="premium-home-section-content">
        <div class="flex justify-center mb-2">
          <div
            class="bg-primary-opacity-10 text-primary py-1 px-4 inline-block rounded-full mb-6 aos-init aos-animate"
            data-aos="fade-right"
            data-aos-duration="1000"
          >
            <a href="#">
              <div class="flex items-center gap-2">
                <div>{{ $t("Features") }}</div>
              </div>
            </a>
          </div>
        </div>
        <div class="flex justify-center">
          <h2
            class="text-center text-3xl md:text-5xl font-bold w-full md:w-[10em] capitalize px-4 md:px-0"
          >
            {{ $t("Whatsapp messaging done differently") }}
          </h2>
        </div>
        <div class="flex justify-center">
          <h3
            class="text-center text-[18px] mt-5 w-full md:w-[33em] px-4 md:px-0"
          >
            {{
              $t(
                "Elevate your communication game and unlock a host of powerful features tailored to enhance your business's WhatsApp experience:",
              )
            }}
          </h3>
        </div>
        <div
          class="premium-home-feature-shell px-5 md:px-10 py-10 grid lg:grid-cols-2 grid-cols-1 gap-6 items-center mt-14"
        >
          <div class="">
            <img
              :src="dashboard3Image"
              :alt="$t('Dashboard')"
              data-aos="fade-right"
              data-aos-duration="400"
              class="aos-init aos-animate"
              loading="lazy"
              decoding="async"
              @error="
                (event) =>
                  onManagedImageError(event, '/images/hero/dashboard3.png')
              "
            />
          </div>
          <div class="lg:ms-24">
            <div
              @click="selectedFeature = 1"
              class="premium-home-accordion-card premium-home-animate bg-white rounded-lg px-2 py-2 mb-5"
            >
              <div class="flex justify-between items-center cursor-pointer">
                <div class="flex items-center gap-x-3">
                  <div
                    class="premium-home-icon-badge bg-secondary-opacity-10 rounded-lg flex items-center justify-center h-10 w-10"
                  >
                    <svg
                      class="h-6 w-6 text-secondary"
                      xmlns="http://www.w3.org/2000/svg"
                      width="1em"
                      height="1em"
                      viewBox="0 0 26 26"
                    >
                      <g fill="currentColor">
                        <path
                          d="M8.05 17.95a1 1 0 1 1 1.414-1.415A5 5 0 0 0 18 13a1 1 0 1 1 2 0a7 7 0 0 1-11.95 4.95"
                        />
                        <path
                          d="M16.559 15.832a1 1 0 1 1-1.11-1.664l3-2a1 1 0 1 1 1.11 1.664z"
                        />
                        <path
                          d="M21.832 15.445a1 1 0 0 1-1.664 1.11l-2-3a1 1 0 1 1 1.664-1.11zm-3.975-7.594a1 1 0 1 1-1.414 1.414a5 5 0 0 0-8.536 3.536a1 1 0 1 1-2 0a7 7 0 0 1 11.95-4.95"
                        />
                        <path
                          d="M9.349 9.969a1 1 0 0 1 1.11 1.664l-3.001 2a1 1 0 1 1-1.11-1.664z"
                        />
                        <path
                          d="M4.075 10.356a1 1 0 1 1 1.664-1.11l2 3a1 1 0 1 1-1.664 1.11z"
                        />
                        <path
                          fill-rule="evenodd"
                          d="M13 24c6.075 0 11-4.925 11-11S19.075 2 13 2S2 6.925 2 13s4.925 11 11 11m0 2c7.18 0 13-5.82 13-13S20.18 0 13 0S0 5.82 0 13s5.82 13 13 13"
                          clip-rule="evenodd"
                        />
                      </g>
                    </svg>
                  </div>
                  <h4 class="font-regular text-gray-800">
                    {{ $t("Instant connectivity") }}
                  </h4>
                </div>
                <div>
                  <svg
                    v-if="selectedFeature != 1"
                    xmlns="http://www.w3.org/2000/svg"
                    width="1em"
                    height="1em"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M16.75 11.989a1.82 1.82 0 0 1-.57 1.36l-6.82 6.1a1.27 1.27 0 0 1-.65.31h-.19a1.3 1.3 0 0 1-.52-.1a1.23 1.23 0 0 1-.54-.47a1.19 1.19 0 0 1-.21-.68v-13a1.2 1.2 0 0 1 .21-.69a1.23 1.23 0 0 1 1.25-.56c.24.039.464.143.65.3l6.76 6.09c.19.162.344.363.45.59c.114.234.175.49.18.75"
                    />
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    width="1em"
                    height="1em"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M19.696 8.72a1.22 1.22 0 0 1-.3.64l-6.09 6.76a1.85 1.85 0 0 1-.58.46a1.7 1.7 0 0 1-1.42.03a1.75 1.75 0 0 1-.62-.42l-6.1-6.83a1.28 1.28 0 0 1-.31-.64a1.31 1.31 0 0 1 .56-1.26a1.36 1.36 0 0 1 .68-.21h13a1.293 1.293 0 0 1 1.15.76c.081.228.092.476.03.71"
                    />
                  </svg>
                </div>
              </div>
              <div v-if="selectedFeature === 1" class="mt-2">
                <p class="text-gray-700 dark:text-gray-300 font-light">
                  {{
                    $t(
                      "Engage with your audience in real-time through the WhatsApp Cloud API, ensuring swift and effective communication",
                    )
                  }}.
                </p>
              </div>
            </div>

            <div
              @click="selectedFeature = 2"
              class="premium-home-accordion-card premium-home-animate bg-white rounded-lg px-2 py-2 mb-5"
            >
              <div class="flex justify-between items-center cursor-pointer">
                <div class="flex items-center gap-x-3">
                  <div
                    class="premium-home-icon-badge bg-secondary-opacity-10 rounded-lg flex items-center justify-center h-10 w-10"
                  >
                    <svg
                      class="h-6 w-6 text-secondary"
                      xmlns="http://www.w3.org/2000/svg"
                      width="1em"
                      height="1em"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="currentColor"
                        d="m8.85 12l1.45-1.45q.3-.3.288-.7t-.288-.7q-.3-.3-.712-.312t-.713.287L6.7 11.3q-.3.3-.3.7t.3.7l2.175 2.175q.3.3.713.288t.712-.313q.275-.3.288-.7t-.288-.7zm6.3 0l-1.45 1.45q-.3.3-.288.7t.288.7q.3.3.713.312t.712-.287L17.3 12.7q.3-.3.3-.7t-.3-.7l-2.175-2.175q-.3-.3-.712-.288t-.713.313q-.275.3-.287.7t.287.7zM5 21q-.825 0-1.412-.587T3 19V5q0-.825.588-1.412T5 3h4.2q.325-.9 1.088-1.45T12 1q.95 0 1.713.55T14.8 3H19q.825 0 1.413.588T21 5v14q0 .825-.587 1.413T19 21zm0-2h14V5H5zm7-14.75q.325 0 .538-.213t.212-.537q0-.325-.213-.537T12 2.75q-.325 0-.537.213t-.213.537q0 .325.213.538T12 4.25M5 19V5z"
                      />
                    </svg>
                  </div>
                  <h4 class="font-regular text-gray-800">
                    {{ $t("Effortless integration") }}
                  </h4>
                </div>
                <div>
                  <svg
                    v-if="selectedFeature != 2"
                    xmlns="http://www.w3.org/2000/svg"
                    width="1em"
                    height="1em"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M16.75 11.989a1.82 1.82 0 0 1-.57 1.36l-6.82 6.1a1.27 1.27 0 0 1-.65.31h-.19a1.3 1.3 0 0 1-.52-.1a1.23 1.23 0 0 1-.54-.47a1.19 1.19 0 0 1-.21-.68v-13a1.2 1.2 0 0 1 .21-.69a1.23 1.23 0 0 1 1.25-.56c.24.039.464.143.65.3l6.76 6.09c.19.162.344.363.45.59c.114.234.175.49.18.75"
                    />
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    width="1em"
                    height="1em"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M19.696 8.72a1.22 1.22 0 0 1-.3.64l-6.09 6.76a1.85 1.85 0 0 1-.58.46a1.7 1.7 0 0 1-1.42.03a1.75 1.75 0 0 1-.62-.42l-6.1-6.83a1.28 1.28 0 0 1-.31-.64a1.31 1.31 0 0 1 .56-1.26a1.36 1.36 0 0 1 .68-.21h13a1.293 1.293 0 0 1 1.15.76c.081.228.092.476.03.71"
                    />
                  </svg>
                </div>
              </div>
              <div v-if="selectedFeature === 2" class="mt-2">
                <p class="text-gray-700 dark:text-gray-300 font-light">
                  {{
                    $t(
                      "Seamlessly integrate our platform into your systems with user-friendly API documentation and expert support",
                    )
                  }}
                </p>
              </div>
            </div>

            <div
              @click="selectedFeature = 3"
              class="premium-home-accordion-card premium-home-animate bg-white rounded-lg px-2 py-2 mb-5"
            >
              <div class="flex justify-between items-center cursor-pointer">
                <div class="flex items-center gap-x-3">
                  <div
                    class="premium-home-icon-badge bg-secondary-opacity-10 rounded-lg flex items-center justify-center h-10 w-10"
                  >
                    <svg
                      class="h-6 w-6 text-secondary"
                      xmlns="http://www.w3.org/2000/svg"
                      width="1em"
                      height="1em"
                      viewBox="0 0 24 24"
                    >
                      <path
                        fill="currentColor"
                        d="M9 13V5c0-1.1.9-2 2-2h9c1.1 0 2 .9 2 2v6h-3.43l-1.28-1.74a.14.14 0 0 0-.24 0L15.06 12c-.06.06-.18.07-.24 0l-1.43-1.75a.152.152 0 0 0-.23 0l-2.11 2.66c-.08.09-.01.24.11.24h6.34V15H11c-1.11 0-2-.89-2-2m-3 9v-1H4v1H2V2h2v1h2V2h2.39C7.54 2.74 7 3.8 7 5v8c0 2.21 1.79 4 4 4h4.7c-1.03.83-1.7 2.08-1.7 3.5c0 .53.11 1.03.28 1.5zM4 7h2V5H4zm0 4h2V9H4zm0 4h2v-2H4zm2 4v-2H4v2zm17-6v2h-2v5.5a2.5 2.5 0 0 1-5 0a2.5 2.5 0 0 1 3.5-2.29V13z"
                      />
                    </svg>
                  </div>
                  <h4 class="font-regular text-gray-800">
                    {{ $t("Multi-media support") }}
                  </h4>
                </div>
                <div>
                  <svg
                    v-if="selectedFeature != 3"
                    xmlns="http://www.w3.org/2000/svg"
                    width="1em"
                    height="1em"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M16.75 11.989a1.82 1.82 0 0 1-.57 1.36l-6.82 6.1a1.27 1.27 0 0 1-.65.31h-.19a1.3 1.3 0 0 1-.52-.1a1.23 1.23 0 0 1-.54-.47a1.19 1.19 0 0 1-.21-.68v-13a1.2 1.2 0 0 1 .21-.69a1.23 1.23 0 0 1 1.25-.56c.24.039.464.143.65.3l6.76 6.09c.19.162.344.363.45.59c.114.234.175.49.18.75"
                    />
                  </svg>
                  <svg
                    v-else
                    xmlns="http://www.w3.org/2000/svg"
                    width="1em"
                    height="1em"
                    viewBox="0 0 24 24"
                  >
                    <path
                      fill="currentColor"
                      d="M19.696 8.72a1.22 1.22 0 0 1-.3.64l-6.09 6.76a1.85 1.85 0 0 1-.58.46a1.7 1.7 0 0 1-1.42.03a1.75 1.75 0 0 1-.62-.42l-6.1-6.83a1.28 1.28 0 0 1-.31-.64a1.31 1.31 0 0 1 .56-1.26a1.36 1.36 0 0 1 .68-.21h13a1.293 1.293 0 0 1 1.15.76c.081.228.092.476.03.71"
                    />
                  </svg>
                </div>
              </div>
              <div v-if="selectedFeature === 3" class="mt-2">
                <p class="text-gray-700 dark:text-gray-300 font-light">
                  {{
                    $t(
                      "Enrich conversations effortlessly by sharing images, documents, and multimedia content, enhancing your overall communication strategy",
                    )
                  }}
                </p>
              </div>
            </div>
          </div>
        </div>
        <div class="grid lg:grid-cols-2 grid-cols-1 gap-6 items-center mt-10">
          <div
            class="premium-home-feature-detail premium-home-animate px-5 md:px-10 py-10 order-2 lg:order-1"
            data-aos="fade-up"
            data-aos-duration="500"
          >
            <div
              class="premium-home-icon-badge bg-secondary-opacity-10 h-12 w-12 bg-primary-opacity-10 flex items-center justify-center rounded-lg"
            >
              <svg
                class="h-6 w-6 text-secondary"
                xmlns="http://www.w3.org/2000/svg"
                width="1em"
                height="1em"
                viewBox="0 0 1024 1024"
              >
                <path
                  fill="currentColor"
                  d="M288 384h448v64H288zm96-128h256v64H384zM131.456 512H384v128h256V512h252.544L721.856 192H302.144zM896 576H704v128H320V576H128v256h768zM275.776 128h472.448a32 32 0 0 1 28.608 17.664l179.84 359.552A32 32 0 0 1 960 519.552V864a32 32 0 0 1-32 32H96a32 32 0 0 1-32-32V519.552a32 32 0 0 1 3.392-14.336l179.776-359.552A32 32 0 0 1 275.776 128"
                />
              </svg>
            </div>

            <h1 class="text-xl/tight font-medium mt-6 mb-4">
              {{ $t("Seamless Bulk and Direct Messaging") }}
            </h1>
            <p class="text-gray-500">
              {{
                $t(
                  "Streamline your communication strategy with our platform's dual capabilities. Engage in effortless one-on-one conversations with your contacts while also harnessing the power to execute targeted bulk messaging campaigns",
                )
              }}
            </p>
          </div>

          <div
            class="premium-home-feature-detail premium-home-animate px-5 md:px-10 py-10 order-2 lg:order-1"
            data-aos="fade-up"
            data-aos-duration="500"
          >
            <div
              class="premium-home-icon-badge bg-secondary-opacity-10 h-12 w-12 bg-primary-opacity-10 flex items-center justify-center rounded-lg"
            >
              <svg
                class="h-6 w-6 text-secondary"
                xmlns="http://www.w3.org/2000/svg"
                width="1em"
                height="1em"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M18.5 10.255c0 .044 0 .089-.003.133A1.537 1.537 0 0 0 17.473 10c-.162 0-.32.025-.473.074V5.75a.75.75 0 0 0-.75-.75h-8.5a.75.75 0 0 0-.75.75v4.505c0 .414.336.75.75.75h8.276l-.01.025l-.003.012l-.45 1.384l-.01.026a1.625 1.625 0 0 1-.019.053H7.75a2.25 2.25 0 0 1-2.25-2.25V5.75A2.25 2.25 0 0 1 7.75 3.5h3.5v-.75a.75.75 0 0 1 .649-.743L12 2a.75.75 0 0 1 .743.649l.007.101l-.001.75h3.5a2.25 2.25 0 0 1 2.25 2.25zm-5.457 3.781l.112-.036H6.254a2.25 2.25 0 0 0-2.25 2.25v.907a3.75 3.75 0 0 0 1.305 2.844c1.563 1.343 3.802 2 6.691 2c2.076 0 3.817-.339 5.213-1.028a1.545 1.545 0 0 1-1.169-1.003l-.004-.012l-.03-.093c-1.086.422-2.42.636-4.01.636c-2.559 0-4.455-.556-5.713-1.638a2.25 2.25 0 0 1-.783-1.706v-.907a.75.75 0 0 1 .75-.75H12v-.003a1.543 1.543 0 0 1 1.031-1.456zM10.999 7.75a1.25 1.25 0 1 0-2.499 0a1.25 1.25 0 0 0 2.499 0m3.243-1.25a1.25 1.25 0 1 1 0 2.499a1.25 1.25 0 0 1 0-2.499m1.847 10.912a2.831 2.831 0 0 0-1.348-.955l-1.377-.448a.544.544 0 0 1 0-1.025l1.377-.448a2.84 2.84 0 0 0 1.76-1.762l.01-.034l.449-1.377a.544.544 0 0 1 1.026 0l.448 1.377a2.837 2.837 0 0 0 1.798 1.796l1.378.448l.027.007a.544.544 0 0 1 0 1.025l-1.378.448a2.839 2.839 0 0 0-1.798 1.796l-.447 1.377a.55.55 0 0 1-.2.263a.544.544 0 0 1-.827-.263l-.448-1.377a2.834 2.834 0 0 0-.45-.848m7.694 3.801l-.765-.248a1.577 1.577 0 0 1-.999-.998l-.249-.765a.302.302 0 0 0-.57 0l-.249.764a1.577 1.577 0 0 1-.983.999l-.766.248a.302.302 0 0 0 0 .57l.766.249a1.576 1.576 0 0 1 .999 1.002l.248.764a.303.303 0 0 0 .57 0l.25-.764a1.575 1.575 0 0 1 .998-.999l.766-.248a.302.302 0 0 0 0-.57z"
                />
              </svg>
            </div>

            <h1 class="text-xl/tight font-medium mt-6 mb-4">
              {{ $t("Craft automated responses") }}
            </h1>
            <p class="text-gray-500">
              {{
                $t(
                  "Take control of your operational efficiency and streamline your workflow effortlessly with our customizable automated response system. Craft responses tailored to your unique needs, guaranteeing swift message delivery to your audience",
                )
              }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <section
      id="section3"
      class="premium-home-section premium-home-section--steps px-5 md:px-10 lg:px-20 py-20 2xl:px-60"
    >
      <div class="premium-home-orbit-layer" aria-hidden="true">
        <span class="premium-home-orbit premium-home-orbit--a"></span>
        <span class="premium-home-orbit premium-home-orbit--b"></span>
        <span class="premium-home-orbit premium-home-orbit--c"></span>
      </div>
      <div class="premium-home-section-content">
        <div class="flex justify-center mb-2">
          <div
            class="bg-primary-opacity-10 text-primary py-1 px-4 inline-block rounded-full mb-6 aos-init aos-animate"
            data-aos="fade-right"
            data-aos-duration="1000"
          >
            <a href="#">
              <div class="flex items-center gap-2">
                <div>{{ $t("How it works") }}</div>
              </div>
            </a>
          </div>
        </div>
        <div class="flex justify-center">
          <h2
            class="text-center text-3xl md:text-5xl font-bold w-full md:w-[11em] px-4 md:px-0"
          >
            {{ $t("A step-by-step guide to our platform") }}
          </h2>
        </div>
        <div class="flex justify-center">
          <h3
            class="text-center text-[18px] mt-5 w-full md:w-[33em] px-4 md:px-0"
          >
            {{
              $t(
                "Explore our platform with ease! Sign up, connect your whatsapp account, and start communicating with your customers",
              )
            }}
          </h3>
        </div>
        <div
          class="flex justify-between items-center gap-6 md:gap-4 lg:gap-6 relative flex-col md:flex-row mt-10 py-8"
        >
          <img
            :src="stepArrowRightImage"
            alt=""
            class="absolute hidden md:block top-14 right-[22%] w-[100px] lg:right-[25%] xl:right-[23%] xl:top-20 2xl:w-[200px]"
            loading="lazy"
            decoding="async"
            @error="
              (event) =>
                onManagedImageError(event, '/images/shapes/stepArrow1.png')
            "
          />
          <img
            :src="stepArrowLeftImage"
            alt=""
            class="absolute hidden md:block top-8 left-[21%] w-[100px] lg:left-[25%] xl:left-[23%] xl:top-20 2xl:w-[200px]"
            loading="lazy"
            decoding="async"
            @error="
              (event) =>
                onManagedImageError(event, '/images/shapes/stepArrow2.png')
            "
          />
          <div
            class="premium-home-step-card premium-home-animate flex justify-center items-center text-center flex-col max-w-[350px]"
          >
            <div
              class="premium-home-step-icon text-secondary bg-white/80 border border-gray-200 rounded-full p-6 lg:p-7 text-3xl lg:text-5xl"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="1em"
                height="1em"
                viewBox="0 0 24 24"
              >
                <defs>
                  <mask id="letsIconsUserScanDuotoneLine0">
                    <g fill="none" stroke-linecap="round">
                      <path
                        stroke="silver"
                        stroke-opacity="0.25"
                        d="M17.5 3.5h.2c1.791 0 2.687 0 3.244.556c.556.557.556 1.453.556 3.244v.2m-4 13h.2c1.791 0 2.687 0 3.244-.556c.556-.557.556-1.453.556-3.244v-.2m-15-13h-.2c-1.791 0-2.687 0-3.243.556C2.5 4.614 2.5 5.51 2.5 7.3v.2m4 13h-.2c-1.791 0-2.687 0-3.243-.556C2.5 19.387 2.5 18.49 2.5 16.7v-.2"
                      />
                      <path
                        stroke="#fff"
                        d="M7.215 15.785c.473-.683 1.16-1.26 2.002-1.665A6.448 6.448 0 0 1 12 13.5c.981 0 1.941.216 2.783.62c.842.405 1.53.982 2.002 1.665"
                      />
                      <circle cx="12" cy="9" r="2.5" stroke="#fff" />
                    </g>
                  </mask>
                </defs>
                <path
                  fill="currentColor"
                  d="M0 0h24v24H0z"
                  mask="url(#letsIconsUserScanDuotoneLine0)"
                />
              </svg>
            </div>
            <h4 class="text-xl font-medium pt-8 pb-6">
              {{ $t("Create an account") }}
            </h4>
            <p class="font-light">
              {{
                $t(
                  "Choose a plan, complete payment securely, then start managing WhatsApp conversations right away.",
                )
              }}
            </p>
          </div>
          <div
            class="premium-home-step-card premium-home-animate flex justify-center items-center text-center flex-col max-w-[350px]"
          >
            <div
              class="premium-home-step-icon text-secondary bg-white/80 border border-gray-200 rounded-full p-6 lg:p-7 text-3xl lg:text-5xl"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="1em"
                height="1em"
                viewBox="0 0 48 48"
              >
                <path
                  fill="none"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M40.47 14.14v-3.21H10.75a3.23 3.23 0 0 0-3.22 3.21h0v18H4v4.94h23.51v-4.95H10.75v-18Z"
                />
                <path
                  fill="none"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M32.14 17.36A1.61 1.61 0 0 0 30.53 19v16.5a1.61 1.61 0 0 0 1.61 1.6h10.25A1.61 1.61 0 0 0 44 35.49V19a1.61 1.61 0 0 0-1.61-1.61h0Zm8.64 14.77h-7V20.58h7Z"
                />
              </svg>
            </div>
            <h4 class="text-xl font-medium pt-8 pb-6">
              {{ $t("Setup your whatsapp instance") }}
            </h4>
            <p class="font-light">
              {{
                $t(
                  "Quick and easy setup for your personalized WhatsApp instance. Begin engaging with your contacts in no time",
                )
              }}.
            </p>
          </div>
          <div
            class="premium-home-step-card premium-home-animate flex justify-center items-center text-center flex-col max-w-[350px]"
          >
            <div
              class="premium-home-step-icon text-secondary bg-white/80 border border-gray-200 rounded-full p-6 lg:p-7 text-3xl lg:text-5xl"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="1em"
                height="1em"
                viewBox="0 0 48 48"
              >
                <path
                  fill="none"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M16.8 29.286c-6.247 0-11.3-5.33-11.3-11.893S10.553 5.5 16.8 5.5h11.757c6.247 0 11.3 5.33 11.3 11.893s-5.053 11.893-11.3 11.893z"
                />
                <path
                  fill="none"
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M24.42 34.092v7.252c0 .63-.508 1.156-1.117 1.156a1.096 1.096 0 0 1-.788-.342l-11.02-11.35c-2.159-2.208-3.352-5.23-3.352-8.356c0-6.438 5.053-11.667 11.3-11.667H31.2c6.247 0 11.3 5.23 11.3 11.667s-5.053 11.666-11.3 11.666h-6.78z"
                />
              </svg>
            </div>
            <h4 class="text-xl font-medium pt-8 pb-6">
              {{ $t("Start your first campaign") }}
            </h4>
            <p class="font-light">
              {{
                $t(
                  "Embark on your journey to success. Initiate your inaugural campaign effortlessly and witness the power of impactful outreach",
                )
              }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <section
      id="section5"
      class="premium-home-section premium-home-section--reviews px-5 md:px-10 lg:px-20 py-20 2xl:px-60"
    >
      <div class="premium-home-orbit-layer" aria-hidden="true">
        <span class="premium-home-orbit premium-home-orbit--a"></span>
        <span class="premium-home-orbit premium-home-orbit--b"></span>
        <span class="premium-home-orbit premium-home-orbit--c"></span>
      </div>
      <div class="premium-home-section-content">
        <div class="flex justify-center mb-2">
          <div
            class="bg-primary-opacity-10 text-primary py-1 px-4 inline-block rounded-full mb-6 aos-init aos-animate"
            data-aos="fade-right"
            data-aos-duration="1000"
          >
            <a href="#">
              <div class="flex items-center gap-2">
                <div>{{ $t("Reviews") }}</div>
              </div>
            </a>
          </div>
        </div>
        <div class="flex justify-center">
          <h2
            class="text-center text-3xl md:text-5xl font-bold w-full md:w-[10em] px-4 md:px-0"
          >
            {{ $t("Explore genuine client feedback") }}
          </h2>
        </div>
        <div class="flex justify-center">
          <h3
            class="text-center text-[18px] mt-5 w-full md:w-[30em] px-4 md:px-0"
          >
            {{
              $t(
                "Discover how businesses like yours transformed with our software. Real stories of growth, innovation, and success",
              )
            }}
          </h3>
        </div>
        <div
          class="md:grid md:grid-cols-2 lg:grid-cols-3 md:gap-6 space-y-4 md:space-y-0 mt-20"
        >
          <div
            v-for="(item, index) in props.reviews"
            :key="index"
            class="premium-home-review-card premium-home-animate bg-white rounded-[15px] p-5"
          >
            <div class="flex">
              <svg
                :class="item.rating >= 1 ? 'ui-text-accent' : 'text-gray-300'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12.86 10.44L11 6.06l-1.86 4.39l-4.75.41L8 14l-1.08 4.63L11 16.17l4.09 2.46L14 14l3.61-3.14zm3.73 10.26L11 17.34L5.42 20.7l1.46-6.35l-4.92-4.28l6.49-.57l2.55-6l2.55 6l6.49.57l-4.92 4.27z"
                />
              </svg>
              <svg
                :class="item.rating >= 2 ? 'ui-text-accent' : 'text-gray-300'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12.86 10.44L11 6.06l-1.86 4.39l-4.75.41L8 14l-1.08 4.63L11 16.17l4.09 2.46L14 14l3.61-3.14zm3.73 10.26L11 17.34L5.42 20.7l1.46-6.35l-4.92-4.28l6.49-.57l2.55-6l2.55 6l6.49.57l-4.92 4.27z"
                />
              </svg>
              <svg
                :class="item.rating >= 3 ? 'ui-text-accent' : 'text-gray-300'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12.86 10.44L11 6.06l-1.86 4.39l-4.75.41L8 14l-1.08 4.63L11 16.17l4.09 2.46L14 14l3.61-3.14zm3.73 10.26L11 17.34L5.42 20.7l1.46-6.35l-4.92-4.28l6.49-.57l2.55-6l2.55 6l6.49.57l-4.92 4.27z"
                />
              </svg>
              <svg
                :class="item.rating >= 4 ? 'ui-text-accent' : 'text-gray-300'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12.86 10.44L11 6.06l-1.86 4.39l-4.75.41L8 14l-1.08 4.63L11 16.17l4.09 2.46L14 14l3.61-3.14zm3.73 10.26L11 17.34L5.42 20.7l1.46-6.35l-4.92-4.28l6.49-.57l2.55-6l2.55 6l6.49.57l-4.92 4.27z"
                />
              </svg>
              <svg
                :class="item.rating == 5 ? 'ui-text-accent' : 'text-gray-300'"
                xmlns="http://www.w3.org/2000/svg"
                width="20"
                height="20"
                viewBox="0 0 24 24"
              >
                <path
                  fill="currentColor"
                  d="M12.86 10.44L11 6.06l-1.86 4.39l-4.75.41L8 14l-1.08 4.63L11 16.17l4.09 2.46L14 14l3.61-3.14zm3.73 10.26L11 17.34L5.42 20.7l1.46-6.35l-4.92-4.28l6.49-.57l2.55-6l2.55 6l6.49.57l-4.92 4.27z"
                />
              </svg>
            </div>
            <p class="font-light">{{ item.review }}</p>
            <div class="flex justify-between items-center mt-5 gap-4">
              <div class="flex items-center gap-3 min-w-0">
                <img
                  :src="resolveReviewImage(item.image)"
                  :alt="item.name || $t('Reviewer')"
                  class="h-12 w-12 rounded-full object-cover bg-slate-100"
                  @error="onReviewImageError"
                />
                <div class="min-w-0">
                  <h3 class="font-semibold truncate">{{ item.name }}</h3>
                  <p class="font-light truncate">{{ item.position }}</p>
                </div>
              </div>
              <div class="shrink-0">
                <svg
                  class="text-gray-500/10"
                  xmlns="http://www.w3.org/2000/svg"
                  width="3em"
                  height="3em"
                  viewBox="0 0 24 24"
                >
                  <path
                    fill="currentColor"
                    d="M19.417 6.679C20.447 7.773 21 9 21 10.989c0 3.5-2.456 6.637-6.03 8.188l-.893-1.378c3.335-1.804 3.987-4.145 4.248-5.621c-.537.278-1.24.375-1.93.311c-1.804-.167-3.226-1.648-3.226-3.489a3.5 3.5 0 0 1 3.5-3.5c1.073 0 2.1.49 2.748 1.179m-10 0C10.447 7.773 11 9 11 10.989c0 3.5-2.456 6.637-6.03 8.188l-.893-1.378c3.335-1.804 3.987-4.145 4.247-5.621c-.537.278-1.24.375-1.929.311C4.591 12.323 3.17 10.842 3.17 9a3.5 3.5 0 0 1 3.5-3.5c1.073 0 2.1.49 2.748 1.179"
                  />
                </svg>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Call to Action Section -->
    <section
      :style="ctaSectionStyle"
      class="premium-home-section premium-home-section--cta relative overflow-hidden px-5 md:px-10 lg:px-20 py-20 2xl:px-60 min-h-[420px] md:min-h-[460px] flex items-center"
    >
      <div
        class="premium-home-cta-overlay absolute inset-0 pointer-events-none"
      ></div>
      <div
        class="premium-home-cta-content relative z-10 max-w-4xl mx-auto text-center"
      >
        <h2 class="text-3xl md:text-5xl font-semibold text-white mb-6">
          {{ $t("Ready to Get Started?") }}
        </h2>
        <p class="text-xl text-white/90 mb-10 max-w-2xl mx-auto">
          {{
            $t(
              "Join thousands of businesses already using our platform to streamline their communication and grow their customer base.",
            )
          }}
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
          <Link
            href="/signup"
            class="premium-home-cta-primary px-6 py-3 bg-white hover:bg-white/90 text-[#3B5BFF] rounded-xl text-base font-semibold transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-105"
          >
            {{ $t("Subscribe now") }}
          </Link>
          <a
            v-if="props.companyConfig && props.companyConfig.book_a_demo_link"
            :href="props.companyConfig.book_a_demo_link"
            target="_blank"
            rel="noopener noreferrer"
            class="premium-home-cta-secondary px-6 py-3 border-2 border-white/70 hover:border-white text-white hover:text-[#3B5BFF] rounded-xl text-base font-semibold transition-all duration-300 bg-white/10 hover:bg-white"
          >
            {{ $t("Book a Demo") }}
          </a>
        </div>
      </div>
    </section>
  </FrontendLayout>
</template>
<script setup>
import { ref, computed } from "vue";
import { Link } from "@inertiajs/vue3";
import FrontendLayout from "./FrontendLayout.vue";

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

const selectedFeature = ref(1);
const showHeroPartners = false;
const heroFeatureCards = [
  {
    title: "Conversation automation",
    description: "Smart replies around the clock to save time and improve the experience.",
    icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 18.5 3.5 21v-4.2A7 7 0 0 1 2 12.5v-1C2 7.36 5.58 4 10 4h4c4.42 0 8 3.36 8 7.5S18.42 19 14 19h-4c-1.83 0-3.52-.58-5-1.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 11.5h.01M12 11.5h.01M16 11.5h.01" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>',
  },
  {
    title: "Advanced analytics",
    description: "Detailed reports and indicators for better data-driven decisions.",
    icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 20V9m7 11V4m7 16v-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M3 20h18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
  },
  {
    title: "Customer management",
    description: "Organize customers and communicate effectively from one platform.",
    icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 19c0-2.2-1.8-4-4-4H8c-2.2 0-4 1.8-4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M10 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm10 8c0-1.9-1.35-3.48-3.14-3.88M15.5 5.25a2.75 2.75 0 0 1 0 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
  {
    title: "Easy integration",
    description: "Connect with your favorite tools and workflows with ease.",
    icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v3m0 12v3m9-9h-3M6 12H3m14.36-5.36-2.12 2.12M8.76 15.24l-2.12 2.12m10.72 0-2.12-2.12M8.76 8.76 6.64 6.64" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="1.7"/></svg>',
  },
  {
    title: "Security and reliability",
    description: "High protection and privacy standards for your customer data.",
    icon: '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 20 6v5c0 5-3.4 8.6-8 10-4.6-1.4-8-5-8-10V6l8-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m8.8 12.1 2.1 2.1 4.6-4.6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  },
];

const defaultReviewAvatar = "/images/defaults/review-avatar.svg";

const resolveReviewImage = (imagePath) => {
  if (!imagePath || typeof imagePath !== "string") {
    return defaultReviewAvatar;
  }

  if (
    imagePath.startsWith("http://") ||
    imagePath.startsWith("https://") ||
    imagePath.startsWith("data:") ||
    imagePath.startsWith("/")
  ) {
    return imagePath;
  }

  return `/storage/${imagePath}`;
};

const onReviewImageError = (event) => {
  if (event?.target && event.target.src !== defaultReviewAvatar) {
    event.target.src = defaultReviewAvatar;
  }
};

const onManagedImageError = (event, fallbackPath) => {
  if (!event?.target) {
    return;
  }

  const fallbackUrl = new URL(fallbackPath, window.location.origin).toString();
  if (event.target.src !== fallbackUrl) {
    event.target.src = fallbackPath;
  }
};

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
const dashboard3Image = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_feature_dashboard",
    "/images/hero/dashboard3.png",
  ),
);
const stepArrowRightImage = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_steps_arrow_right",
    "/images/shapes/stepArrow1.png",
  ),
);
const stepArrowLeftImage = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_steps_arrow_left",
    "/images/shapes/stepArrow2.png",
  ),
);
const heroBackgroundImage = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_hero_background",
    "/images/hero/hero-background-default.svg",
  ),
);
const ctaBackgroundImage = computed(() =>
  resolvePremiumHomeImage(
    "premium_home_cta_background",
    heroBackgroundImage.value,
  ),
);
const heroSectionStyle = computed(() => ({
  backgroundImage: `url(${heroBackgroundImage.value})`,
  backgroundPosition: "center top",
  backgroundSize: "cover",
  backgroundRepeat: "no-repeat",
}));
const ctaSectionStyle = computed(() => ({
  backgroundImage: `url(${ctaBackgroundImage.value})`,
  backgroundSize: "cover",
  backgroundPosition: "center center",
  backgroundRepeat: "no-repeat",
}));
</script>
