<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-8 lg:gap-10">
                <div class="flex w-full max-w-[1120px]">
                    <PageBreadcrumb :current-label="pageTitle" />
                </div>

                <div class="flex w-full flex-col items-center gap-4 text-center lg:w-[1120px]">
                    <SectionBadge :label="pageTitle" />
                    <h1 class="w-full text-[28px] font-semibold leading-[1.3] text-black dark:text-white lg:text-[46px] lg:leading-[56px]">
                        {{ pageTitle }}
                    </h1>
                    <p dir="auto" class="w-full text-sm leading-6 text-[#445566] dark:text-[#94a3b8] lg:text-[24px] lg:leading-9">
                        {{ $t('Last Updated') }}: {{ formatDate(props.page.data.updated_at) }}
                    </p>
                </div>

                <!-- Sectioned layout: table of contents + numbered section cards -->
                <div v-if="hasSections" class="flex w-full flex-col-reverse items-start gap-6 lg:w-[1120px] lg:flex-row lg:justify-between">
                    <div class="flex w-full flex-col items-end gap-6 lg:w-[799px]">
                        <article
                            v-for="(section, index) in sections"
                            :id="section.id"
                            :key="section.id"
                            :ref="(el) => setSectionRef(el, index)"
                            class="section-content w-full scroll-mt-24 rounded-3xl p-6 text-right transition-colors duration-300"
                            :class="index === activeIndex ? 'border-r-[3px] border-[#2bd46a] bg-[rgba(37,211,102,0.12)]' : ''"
                        >
                            <h2 dir="auto" class="w-full text-xl font-bold leading-[1.4] text-black dark:text-white lg:text-2xl">
                                {{ index + 1 }}- {{ section.title }}
                            </h2>
                            <div dir="auto" class="mt-4 w-full" v-html="section.bodyHtml"></div>
                        </article>
                    </div>

                    <aside class="sticky top-24 hidden w-[263px] shrink-0 flex-col gap-3 rounded-[10px] border border-[#cfd8e3] bg-[#f7f9fb] px-5 py-6 dark:border-[#1a2332] dark:bg-[#0d1117] lg:flex">
                        <a
                            v-for="(section, index) in sections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="flex h-9 w-full items-center justify-end overflow-hidden p-2 text-right transition-colors"
                            :class="activeIndex === index
                                ? 'rounded-lg border-r-2 border-[#2bd46a] bg-[rgba(37,211,102,0.12)] text-black dark:text-white'
                                : 'rounded-md text-[#8899aa] hover:text-black dark:hover:text-white'"
                            @click="goToSection(index, $event)"
                        >
                            <span dir="auto" class="w-full truncate text-base leading-6">{{ section.title }}</span>
                        </a>
                    </aside>
                </div>

                <!-- Flat fallback layout for content with no section headings -->
                <div
                    v-else
                    dir="auto"
                    class="page-content w-full text-right lg:w-[1120px]"
                    v-html="rawContent"
                ></div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PageBreadcrumb from '@/Components/PageBreadcrumb.vue';
    import { usePage } from '@inertiajs/vue3';

    const props = defineProps({ page: Object, companyConfig: Object, pages: Object });
    const page = usePage();

    const pageTitle = computed(() => props.page.data.display_name || props.page.data.name);
    const rawContent = computed(() => props.page.data.display_content || props.page.data.content || '');
    const activeIndex = ref(0);
    const sectionEls = ref([]);
    let scrollSpyObserver = null;
    let suppressScrollSpy = false;

    const setSectionRef = (el, index) => {
        if (el) sectionEls.value[index] = el;
    };

    const goToSection = (index, event) => {
        event.preventDefault();
        activeIndex.value = index;
        suppressScrollSpy = true;
        sectionEls.value[index]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        window.setTimeout(() => { suppressScrollSpy = false; }, 700);
    };

    const teardownScrollSpy = () => {
        scrollSpyObserver?.disconnect();
        scrollSpyObserver = null;
    };

    // Keeps the active tab/highlighted card in sync with whichever section
    // is currently scrolled into view, not just the one that was clicked.
    const setupScrollSpy = () => {
        teardownScrollSpy();
        if (typeof IntersectionObserver === 'undefined' || !sectionEls.value.length) return;

        scrollSpyObserver = new IntersectionObserver((entries) => {
            if (suppressScrollSpy) return;

            const visible = entries.filter((entry) => entry.isIntersecting);
            if (!visible.length) return;

            const topMost = visible.reduce((closest, entry) => (
                entry.boundingClientRect.top < closest.boundingClientRect.top ? entry : closest
            ));
            const index = sectionEls.value.indexOf(topMost.target);
            if (index !== -1) activeIndex.value = index;
        }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

        sectionEls.value.forEach((el) => el && scrollSpyObserver.observe(el));
    };

    // Content authored with <h2> headings renders as a table-of-contents +
    // numbered section cards (matches the Terms of Use Figma spec); content
    // without headings falls back to the plain flowing layout used for
    // simpler pages like the privacy policy.
    const sections = computed(() => {
        if (typeof window === 'undefined' || !rawContent.value) return [];

        const doc = new DOMParser().parseFromString(rawContent.value, 'text/html');
        const built = [];
        let current = null;

        Array.from(doc.body.children).forEach((node) => {
            if (node.tagName === 'H2') {
                current = { title: node.textContent.trim(), bodyParts: [] };
                built.push(current);
            } else if (current) {
                current.bodyParts.push(node.outerHTML);
            }
        });

        return built.map((section, index) => ({
            id: `section-${index + 1}`,
            title: section.title,
            bodyHtml: section.bodyParts.join(''),
        }));
    });

    const hasSections = computed(() => sections.value.length >= 2);

    watch(hasSections, (value) => {
        if (!value) {
            teardownScrollSpy();
            return;
        }

        sectionEls.value = [];
        nextTick(() => setupScrollSpy());
    }, { immediate: true });

    onBeforeUnmount(() => teardownScrollSpy());

    const formatDate = (dateString) => {
        if (!dateString) return '';
        const date = new Date(dateString);
        const locale = page.props?.currentLanguage === 'ar' ? 'ar-EG' : 'en-US';
        return date.toLocaleDateString(locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };
</script>

<style scoped>
.page-content :deep(p),
.page-content :deep(li) {
    font-size: 1rem;
    line-height: 1.6;
    color: #445566;
}

.page-content :deep(p + p),
.page-content :deep(ul + p),
.page-content :deep(p + ul),
.page-content :deep(h2 + p) {
    margin-top: 1.5rem;
}

.page-content :deep(h1),
.page-content :deep(h2),
.page-content :deep(h3) {
    font-weight: 600;
    color: #000000;
    margin-top: 2rem;
    margin-bottom: 1rem;
}

.page-content :deep(h1) { font-size: 1.75rem; }
.page-content :deep(h2) { font-size: 1.5rem; }
.page-content :deep(h3) { font-size: 1.25rem; }

.page-content :deep(a) {
    color: #25d366;
    text-decoration: underline;
}

.page-content :deep(a:hover) {
    color: #1db954;
}

.page-content :deep(ul),
.page-content :deep(ol) {
    margin-top: 1rem;
    margin-bottom: 1rem;
    padding-inline-start: 1.5rem;
}

.page-content :deep(strong) {
    font-weight: 600;
    color: #000000;
}

.page-content :deep(blockquote) {
    border-inline-start: 3px solid #5a6b7e;
    padding-inline-start: 1rem;
    font-style: italic;
    color: #445566;
}

.page-content :deep(img) {
    border-radius: 0.75rem;
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
}

@media (min-width: 1024px) {
    .page-content :deep(p),
    .page-content :deep(li) {
        font-size: 1.5rem;
        line-height: 1.5;
    }

    .page-content :deep(p + p),
    .page-content :deep(ul + p),
    .page-content :deep(p + ul),
    .page-content :deep(h2 + p) {
        margin-top: 2.5rem;
    }
}

:global(.dark) .page-content :deep(p),
:global(.dark) .page-content :deep(li) {
    color: #94a3b8;
}

:global(.dark) .page-content :deep(h1),
:global(.dark) .page-content :deep(h2),
:global(.dark) .page-content :deep(h3),
:global(.dark) .page-content :deep(strong) {
    color: #ffffff;
}

/* Section cards (Terms of Use-style pages): Figma spec is 18px text with a
   deliberately loose 36px line-height, distinct from the flat layout above. */
.section-content :deep(p),
.section-content :deep(li) {
    font-size: 1rem;
    line-height: 1.6;
    color: #445566;
}

.section-content :deep(p + p),
.section-content :deep(ul + p),
.section-content :deep(p + ul) {
    margin-top: 1rem;
}

.section-content :deep(h3) {
    font-weight: 600;
    color: #000000;
    font-size: 1.125rem;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

.section-content :deep(a) {
    color: #25d366;
    text-decoration: underline;
}

.section-content :deep(a:hover) {
    color: #1db954;
}

.section-content :deep(ul),
.section-content :deep(ol) {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    padding-inline-start: 1.5rem;
}

.section-content :deep(strong) {
    font-weight: 600;
    color: #000000;
}

@media (min-width: 1024px) {
    .section-content :deep(p),
    .section-content :deep(li) {
        font-size: 1.125rem;
        line-height: 2;
    }

    .section-content :deep(p + p),
    .section-content :deep(ul + p),
    .section-content :deep(p + ul) {
        margin-top: 1.5rem;
    }
}

:global(.dark) .section-content :deep(p),
:global(.dark) .section-content :deep(li) {
    color: #94a3b8;
}

:global(.dark) .section-content :deep(h3),
:global(.dark) .section-content :deep(strong) {
    color: #ffffff;
}

:global(.dark) .page-content :deep(blockquote) {
    border-inline-start-color: #94a3b8;
    color: #94a3b8;
}
</style>
