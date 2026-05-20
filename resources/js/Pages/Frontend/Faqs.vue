<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero Section -->
        <section class="relative px-5 md:px-10 lg:px-20 pt-32 pb-10 2xl:px-60 bg-gradient-section">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-3xl md:text-6xl leading-[1.3] font-semibold mb-6 text-gray-900">
                    {{ $t('Frequently Asked') }}<br>{{ $t('Questions') }}
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Find answers to common questions about our platform, features, and how to get started') }}
                </p>
            </div>
        </section>

        <!-- FAQs Section -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60" v-if="props.faqs && props.faqs.data && props.faqs.data.length > 0">
            <div class="max-w-4xl mx-auto">
                <div class="space-y-4">
                    <div 
                        v-for="(item, index) in props.faqs.data" 
                        :key="index" 
                        class="bg-white rounded-2xl border border-gray-200 overflow-hidden transition-all duration-300"
                        :class="selectedFaq === index+1 ? 'shadow-lg' : 'hover:shadow-md'"
                    >
                        <button type="button" 
                            @click="selectedFaq = selectedFaq === index+1 ? null : index+1" 
                            class="w-full inline-flex p-6 md:p-8 items-start justify-between text-start transition-colors hover:bg-gray-50"
                        >
                            <div class="flex-1 pe-4">
                                <h3 class="text-lg font-semibold text-gray-900" v-html="item.question"></h3>
                            </div>
                            <div class="flex-shrink-0 ms-4">
                                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center transition-transform duration-300"
                                     :class="selectedFaq === index+1 ? 'bg-primary-opacity-10 rotate-180' : ''">
                                    <svg class="w-5 h-5 text-gray-600 transition-colors" 
                                         :class="selectedFaq === index+1 ? 'text-primary' : ''"
                                         xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </div>
                            </div>
                        </button>
                        <div 
                            class="w-full overflow-hidden transition-all duration-300"
                            :class="selectedFaq === index+1 ? 'max-h-[1000px] opacity-100' : 'max-h-0 opacity-0'"
                        >
                            <div class="px-6 md:px-8 pb-6 md:pb-8">
                                <div class="ui-border-start ui-border-start-2 border-gray-200 ps-6">
                                    <div class="text-gray-600 leading-relaxed prose prose-sm max-w-none" v-html="item.answer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact CTA -->
                <div class="mt-16 text-center">
                    <div class="bg-gray-100 rounded-2xl p-8 border border-gray-200">
                        <h3 class="text-xl font-semibold text-gray-900 mb-3">{{ $t('Still have questions?') }}</h3>
                        <p class="text-gray-600 mb-6">{{ $t('Can\'t find the answer you\'re looking for? Our support team is here to help.') }}</p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <Link 
                                href="/contact" 
                                class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg text-sm font-medium transition-colors"
                            >
                                {{ $t('Contact Us') }}
                            </Link>
                            <a 
                                :href="'mailto:' + props.companyConfig.email" 
                                class="px-6 py-3 border border-gray-300 hover:border-primary text-gray-700 hover:text-primary rounded-lg text-sm font-medium transition-colors bg-white"
                            >
                                {{ $t('Send Email') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Empty State -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60" v-else>
            <div class="max-w-4xl mx-auto text-center">
                <div class="bg-gray-100 rounded-2xl p-12 border border-gray-200">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $t('No FAQs Available') }}</h3>
                    <p class="text-gray-600 mb-6">{{ $t('We\'re currently updating our FAQ section. Please contact us if you have any questions.') }}</p>
                    <Link href="/contact" class="inline-block px-6 py-3 bg-primary hover:bg-secondary text-white rounded-lg text-sm font-medium transition-colors">
                        {{ $t('Contact Us') }}
                    </Link>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { ref } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import FrontendLayout from './FrontendLayout.vue';

    const props = defineProps(['companyConfig', 'pages', 'faqs']);
    const selectedFaq = ref(null);
</script>

<style scoped>
:deep(.prose) {
    max-width: none;
}

:deep(.prose p) {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    line-height: 1.7;
}

:deep(.prose ul),
:deep(.prose ol) {
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    padding-left: 1.5rem;
}

:deep(.prose li) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

:deep(.prose strong) {
    font-weight: 600;
    color: var(--ui-text);
}

:deep(.prose a) {
    color: var(--ui-primary);
    text-decoration: underline;
}

:deep(.prose a:hover) {
    color: var(--ui-secondary);
}
</style>

