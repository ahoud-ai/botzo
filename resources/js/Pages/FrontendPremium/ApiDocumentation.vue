<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 pt-32 pb-8 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-4 lg:gap-6">
                <PageBreadcrumb :current-label="$t('API Documentation')" />

                <div class="flex w-full flex-col items-center gap-3 text-center lg:w-[1120px] lg:gap-4">
                    <SectionBadge :label="$t('API Documentation')" />
                    <h1 class="w-full text-[28px] font-semibold leading-[1.3] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ $t('Developer API') }} {{ $t('Reference') }}
                    </h1>
                    <p dir="auto" class="w-full text-base leading-6 text-[#8899aa] lg:text-[24px] lg:leading-9">
                        {{ $t('Integrate with our REST API to send messages, manage contacts, and automate your WhatsApp communication workflows') }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Documentation -->
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex w-full flex-col-reverse items-start gap-6 lg:mx-auto lg:w-[1120px] lg:flex-row lg:justify-between">
                <!-- Main content -->
                <div class="flex w-full flex-col items-end gap-6 lg:w-[799px]">
                    <!-- Authentication -->
                    <article
                        id="authentication"
                        :ref="(el) => setSectionRef(el, 'authentication')"
                        class="section-content w-full scroll-mt-24 rounded-3xl p-6 text-right"
                        :class="activeSection === 'authentication' ? 'border-r-[3px] border-[#2bd46a] bg-[rgba(37,211,102,0.12)]' : ''"
                    >
                        <h2 dir="auto" class="w-full text-xl font-bold leading-[1.4] text-black dark:text-white lg:text-2xl">
                            {{ $t('Authentication') }}
                        </h2>
                        <p dir="auto" class="mt-4 w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8] lg:text-[18px] lg:leading-9">
                            {{ $t('All API requests require authentication using a Bearer token. Include your API key in the Authorization header:') }}
                        </p>
                        <div class="mt-4 w-full overflow-x-auto rounded-2xl bg-[#0d1117] p-4" dir="ltr">
                            <code class="text-sm text-[#25d366]">Authorization: Bearer your-api-key</code>
                        </div>
                        <p dir="auto" class="mt-4 w-full text-sm leading-6 text-[#8899aa]">
                            {{ $t('To generate an API key, log in to your dashboard and navigate to Developer Tools > Access Tokens') }}
                        </p>
                    </article>

                    <!-- Endpoint groups -->
                    <article
                        v-for="(section, sectionIndex) in props.apirequests"
                        :key="sectionIndex"
                        :id="'section-' + sectionIndex"
                        :ref="(el) => setSectionRef(el, 'section-' + sectionIndex)"
                        class="section-content w-full scroll-mt-24 rounded-3xl p-6 text-right"
                        :class="activeSection === ('section-' + sectionIndex) ? 'border-r-[3px] border-[#2bd46a] bg-[rgba(37,211,102,0.12)]' : ''"
                    >
                        <h2 dir="auto" class="w-full text-xl font-bold capitalize leading-[1.4] text-black dark:text-white lg:text-2xl">
                            {{ $t(section.title) }}
                        </h2>

                        <div class="mt-6 flex w-full flex-col gap-6">
                            <div
                                v-for="(endpoint, endpointIndex) in section.value"
                                :key="endpointIndex"
                                class="flex w-full flex-col items-end gap-4 rounded-2xl border border-[#cfd8e3] bg-white p-5 dark:border-[#1e2a3a] dark:bg-[#0a0f17]"
                            >
                                <div class="flex w-full items-center justify-end gap-3" dir="ltr">
                                    <code class="truncate font-mono text-sm text-black dark:text-white">{{ endpoint.route }}</code>
                                    <span class="shrink-0 rounded-md bg-[#25d366] px-3 py-1 text-xs font-semibold uppercase text-[#04130a]">{{ endpoint.method }}</span>
                                </div>

                                <h3 dir="auto" class="w-full text-base font-semibold text-black dark:text-white">{{ $t(endpoint.title) }}</h3>

                                <div class="w-full">
                                    <div class="flex gap-x-1 overflow-x-auto border-b border-[#cfd8e3] dark:border-[#1e2a3a]" dir="ltr">
                                        <button
                                            type="button"
                                            v-for="lang in ['curl', 'php', 'nodejs', 'python', 'java', 'ruby']"
                                            :key="lang"
                                            @click="selectedLanguage = lang"
                                            class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                                            :class="selectedLanguage === lang
                                                ? 'border-[#25d366] text-[#25d366]'
                                                : 'border-transparent text-[#8899aa] hover:text-black dark:hover:text-white'"
                                        >
                                            {{ lang.toUpperCase() }}
                                        </button>
                                    </div>

                                    <div class="mt-4 w-full overflow-x-auto rounded-2xl bg-[#0d1117] p-4" dir="ltr">
                                        <pre class="whitespace-pre-wrap font-mono text-xs text-[#25d366]"><code>{{ getCodeExample(endpoint, selectedLanguage) }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>

                    <!-- Base URL -->
                    <article
                        id="base-url"
                        :ref="(el) => setSectionRef(el, 'base-url')"
                        class="section-content w-full scroll-mt-24 rounded-3xl p-6 text-right"
                        :class="activeSection === 'base-url' ? 'border-r-[3px] border-[#2bd46a] bg-[rgba(37,211,102,0.12)]' : ''"
                    >
                        <h2 dir="auto" class="w-full text-xl font-bold leading-[1.4] text-black dark:text-white lg:text-2xl">
                            {{ $t('Base URL') }}
                        </h2>
                        <p dir="auto" class="mt-4 w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8] lg:text-[18px] lg:leading-9">
                            {{ $t('All API requests should be made to:') }}
                        </p>
                        <code dir="ltr" class="mt-2 inline-block rounded-lg border border-[#cfd8e3] bg-white px-3 py-2 font-mono text-sm text-black dark:border-[#1e2a3a] dark:bg-[#0a0f17] dark:text-white">{{ baseUrl }}/api</code>
                    </article>
                </div>

                <!-- TOC sidebar -->
                <aside class="sticky top-24 hidden w-[263px] shrink-0 flex-col gap-3 rounded-[10px] border border-[#cfd8e3] bg-[#f7f9fb] px-5 py-6 dark:border-[#1a2332] dark:bg-[#0d1117] lg:flex">
                    <a
                        href="#authentication"
                        class="flex h-9 w-full items-center justify-end overflow-hidden p-2 text-right transition-colors"
                        :class="activeSection === 'authentication'
                            ? 'rounded-lg border-r-2 border-[#2bd46a] bg-[rgba(37,211,102,0.12)] text-black dark:text-white'
                            : 'rounded-md text-[#8899aa] hover:text-black dark:hover:text-white'"
                        @click="goToSection('authentication', $event)"
                    >
                        <span dir="auto" class="w-full truncate text-base leading-6">{{ $t('Authentication') }}</span>
                    </a>
                    <a
                        v-for="(section, sectionIndex) in props.apirequests"
                        :key="sectionIndex"
                        :href="'#section-' + sectionIndex"
                        class="flex h-9 w-full items-center justify-end overflow-hidden p-2 text-right capitalize transition-colors"
                        :class="activeSection === ('section-' + sectionIndex)
                            ? 'rounded-lg border-r-2 border-[#2bd46a] bg-[rgba(37,211,102,0.12)] text-black dark:text-white'
                            : 'rounded-md text-[#8899aa] hover:text-black dark:hover:text-white'"
                        @click="goToSection('section-' + sectionIndex, $event)"
                    >
                        <span dir="auto" class="w-full truncate text-base leading-6">{{ $t(section.title) }}</span>
                    </a>
                    <a
                        href="#base-url"
                        class="flex h-9 w-full items-center justify-end overflow-hidden p-2 text-right transition-colors"
                        :class="activeSection === 'base-url'
                            ? 'rounded-lg border-r-2 border-[#2bd46a] bg-[rgba(37,211,102,0.12)] text-black dark:text-white'
                            : 'rounded-md text-[#8899aa] hover:text-black dark:hover:text-white'"
                        @click="goToSection('base-url', $event)"
                    >
                        <span dir="auto" class="w-full truncate text-base leading-6">{{ $t('Base URL') }}</span>
                    </a>
                </aside>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { resolveApiDocExample } from '../../Utils/apiDocumentationExamples.js';
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PageBreadcrumb from '@/Components/PageBreadcrumb.vue';

    const { t } = useI18n();

    const props = defineProps(['companyConfig', 'pages', 'apirequests']);

    const selectedLanguage = ref('curl');
    const activeSection = ref('authentication');
    const sectionEls = ref({});
    let scrollSpyObserver = null;
    let suppressScrollSpy = false;

    const baseUrl = computed(() => window.location.origin);

    const getCodeExample = (endpoint, language) => {
        if (!endpoint.request || !endpoint.request[language]) {
            return '// ' + t('Code example not available');
        }

        return resolveApiDocExample(endpoint.request[language], baseUrl.value);
    };

    const setSectionRef = (el, id) => {
        if (el) sectionEls.value[id] = el;
    };

    const goToSection = (id, event) => {
        event.preventDefault();
        activeSection.value = id;
        suppressScrollSpy = true;
        sectionEls.value[id]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.setTimeout(() => { suppressScrollSpy = false; }, 700);
    };

    const setupScrollSpy = () => {
        scrollSpyObserver?.disconnect();
        const targets = Object.values(sectionEls.value);
        if (typeof IntersectionObserver === 'undefined' || !targets.length) return;

        scrollSpyObserver = new IntersectionObserver((entries) => {
            if (suppressScrollSpy) return;

            const visible = entries.filter((entry) => entry.isIntersecting);
            if (!visible.length) return;

            const topMost = visible.reduce((closest, entry) => (
                entry.boundingClientRect.top < closest.boundingClientRect.top ? entry : closest
            ));
            const match = Object.entries(sectionEls.value).find(([, el]) => el === topMost.target);
            if (match) activeSection.value = match[0];
        }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

        targets.forEach((el) => scrollSpyObserver.observe(el));
    };

    onMounted(() => nextTick(() => setupScrollSpy()));
    onBeforeUnmount(() => scrollSpyObserver?.disconnect());
</script>

<style scoped>
.section-content :deep(p),
.section-content :deep(li) {
    color: #445566;
}

:global(.dark) .section-content :deep(p),
:global(.dark) .section-content :deep(li) {
    color: #94a3b8;
}
</style>
