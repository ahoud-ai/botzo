<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <section class="px-5 md:px-10 lg:px-20 2xl:px-60 py-12 md:py-16 lg:py-20 bg-white dark:bg-[#0a0f17]">
            <div class="flex flex-col items-center gap-10 lg:gap-[72px]">
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

                <div
                    dir="auto"
                    class="page-content w-full text-right lg:w-[1120px]"
                    v-html="props.page.data.display_content || props.page.data.content"
                ></div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { computed } from 'vue';
    import FrontendLayout from './FrontendLayout.vue';
    import SectionBadge from '@/Components/SectionBadge.vue';
    import PageBreadcrumb from '@/Components/PageBreadcrumb.vue';
    import { usePage } from '@inertiajs/vue3';

    const props = defineProps({ page: Object, companyConfig: Object, pages: Object });
    const page = usePage();

    const pageTitle = computed(() => props.page.data.display_name || props.page.data.name);

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

:global(.dark) .page-content :deep(blockquote) {
    border-inline-start-color: #94a3b8;
    color: #94a3b8;
}
</style>
