<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        
        <!-- Page Content -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="max-w-4xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-3xl md:text-5xl font-semibold text-gray-900 mb-4 text-center">{{ props.page.data.display_name || props.page.data.name }}</h1>
                    <p class="text-center text-gray-500 text-sm">{{ $t('Last Updated') }}: {{ formatDate(props.page.data.updated_at) }}</p>
                </div>
                <div class="prose prose-lg max-w-none" v-html="props.page.data.display_content || props.page.data.content"></div>
            </div>
        </section>

    </FrontendLayout>
</template>

<script setup>
    import FrontendLayout from './FrontendLayout.vue';
    import { usePage } from '@inertiajs/vue3';

    const props = defineProps({ page: Object, companyConfig: Object, pages: Object });
    const page = usePage();

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
/* Prose styles for content */
:deep(.prose) {
    color: var(--ui-muted);
    line-height: 1.75;
}

:deep(.prose h1) {
    font-size: 2.25em;
    margin-top: 0;
    margin-bottom: 0.8888889em;
    font-weight: 600;
    color: var(--ui-text);
}

:deep(.prose h2) {
    font-size: 1.5em;
    margin-top: 2em;
    margin-bottom: 1em;
    font-weight: 600;
    color: var(--ui-text);
}

:deep(.prose h3) {
    font-size: 1.25em;
    margin-top: 1.6em;
    margin-bottom: 0.6em;
    font-weight: 600;
    color: var(--ui-text);
}

:deep(.prose p) {
    margin-top: 1.25em;
    margin-bottom: 1.25em;
}

:deep(.prose a) {
    color: var(--ui-primary);
    text-decoration: underline;
}

:deep(.prose a:hover) {
    color: var(--ui-secondary);
}

:deep(.prose ul, .prose ol) {
    margin-top: 1.25em;
    margin-bottom: 1.25em;
    padding-left: 1.625em;
}

:deep(.prose li) {
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

:deep(.prose strong) {
    font-weight: 600;
    color: var(--ui-text);
}

:deep(.prose code) {
    background-color: color-mix(in srgb, #fff 88%, var(--ui-primary) 12%);
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-size: 0.875em;
    font-weight: 600;
    color: var(--ui-text);
}

:deep(.prose pre) {
    background-color: color-mix(in srgb, var(--ui-text) 86%, #000 14%);
    color: color-mix(in srgb, #fff 94%, var(--ui-primary) 6%);
    overflow-x: auto;
    font-weight: 400;
    font-size: 0.875em;
    line-height: 1.7142857;
    margin-top: 1.7142857em;
    margin-bottom: 1.7142857em;
    border-radius: 0.375rem;
    padding: 0.8571429em 1.1428571em;
}

:deep(.prose pre code) {
    background-color: transparent;
    border-width: 0;
    border-radius: 0;
    padding: 0;
    font-weight: inherit;
    color: inherit;
    font-size: inherit;
    font-family: inherit;
    line-height: inherit;
}

:deep(.prose blockquote) {
    font-weight: 500;
    font-style: italic;
    color: var(--ui-text);
    border-left-width: 0.25rem;
    border-left-color: color-mix(in srgb, var(--ui-border-strong) 70%, #fff 30%);
    quotes: "\201C""\201D""\2018""\2019";
    margin-top: 1.6em;
    margin-bottom: 1.6em;
    padding-left: 1em;
}

:deep(.prose img) {
    margin-top: 2em;
    margin-bottom: 2em;
    border-radius: 0.5rem;
}

:deep(.prose table) {
    width: 100%;
    table-layout: auto;
    text-align: start;
    margin-top: 2em;
    margin-bottom: 2em;
    font-size: 0.875em;
    line-height: 1.7142857;
}

:deep(.prose thead) {
    border-bottom-width: 1px;
    border-bottom-color: color-mix(in srgb, var(--ui-border) 90%, #fff 10%);
}

:deep(.prose thead th) {
    color: var(--ui-text);
    font-weight: 600;
    vertical-align: bottom;
    padding-right: 0.5714286em;
    padding-bottom: 0.5714286em;
    padding-left: 0.5714286em;
}

:deep(.prose tbody tr) {
    border-bottom-width: 1px;
    border-bottom-color: color-mix(in srgb, var(--ui-border) 82%, #fff 18%);
}

:deep(.prose tbody td) {
    vertical-align: baseline;
    padding-top: 0.5714286em;
    padding-right: 0.5714286em;
    padding-bottom: 0.5714286em;
    padding-left: 0.5714286em;
}
</style>
