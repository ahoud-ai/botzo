<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">
        <!-- Hero Section -->
        <section class="relative px-5 md:px-10 lg:px-20 pt-32 pb-10 2xl:px-60 bg-gradient-section">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-3xl md:text-6xl leading-[1.3] font-semibold mb-6 text-gray-900">
                    {{ $t('Developer API') }}<br>{{ $t('Reference') }}
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Integrate with our REST API to send messages, manage contacts, and automate your WhatsApp communication workflows') }}
                </p>
            </div>
        </section>

        <!-- API Documentation Content -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="max-w-7xl mx-auto flex gap-8">
                <!-- Side Menu -->
                <aside class="hidden lg:block w-64 flex-shrink-0 sticky top-24 h-fit">
                    <div class="bg-gray-100 rounded-2xl p-4 border border-gray-200">
                        <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-4">{{ $t('Quick Navigation') }}</h3>
                        <nav class="space-y-1">
                            <a 
                                href="#authentication" 
                                @click.prevent="scrollToSection('authentication')"
                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-white hover:text-primary rounded-lg transition-colors"
                            >
                                {{ $t('Authentication') }}
                            </a>
                            <template v-for="(section, sectionIndex) in props.apirequests" :key="sectionIndex">
                                <a 
                                    :href="'#section-' + sectionIndex" 
                                    @click.prevent="scrollToSection('section-' + sectionIndex)"
                                    class="block px-3 py-2 text-sm text-gray-700 hover:bg-white hover:text-primary rounded-lg transition-colors capitalize"
                                >
                                    {{ $t(section.title) }}
                                </a>
                            </template>
                            <a 
                                href="#base-url" 
                                @click.prevent="scrollToSection('base-url')"
                                class="block px-3 py-2 text-sm text-gray-700 hover:bg-white hover:text-primary rounded-lg transition-colors"
                            >
                                {{ $t('Base URL') }}
                            </a>
                        </nav>
                    </div>
                </aside>

                <!-- Main Content -->
                <div class="flex-1 min-w-0">
                <!-- Authentication Section -->
                <div id="authentication" class="mb-12 scroll-mt-24">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ $t('Authentication') }}</h2>
                    <div class="bg-gray-100 rounded-2xl p-6 border border-gray-200">
                        <p class="text-gray-700 mb-4">
                            {{ $t('All API requests require authentication using a Bearer token. Include your API key in the Authorization header:') }}
                        </p>
                        <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                            <code class="text-green-400 text-sm">
                                Authorization: Bearer your-api-key
                            </code>
                        </div>
                        <p class="text-gray-600 text-sm mt-4">
                            {{ $t('To generate an API key, log in to your dashboard and navigate to Developer Tools > Access Tokens') }}
                        </p>
                    </div>
                </div>

                <!-- API Endpoints -->
                <div class="space-y-12">
                    <div v-for="(section, sectionIndex) in props.apirequests" :key="sectionIndex" :id="'section-' + sectionIndex" class="border-b border-gray-200 pb-12 last:border-b-0 scroll-mt-24">
                        <h2 class="text-2xl font-bold text-gray-900 mb-6 capitalize">{{ $t(section.title) }}</h2>
                        
                        <div v-for="(endpoint, endpointIndex) in section.value" :key="endpointIndex" class="mb-8">
                            <div class="bg-gray-100 rounded-2xl p-6 border border-gray-200">
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="rounded-md bg-primary px-3 py-1 text-xs font-semibold text-white uppercase">{{ endpoint.method }}</span>
                                    <code class="text-gray-900 font-mono text-sm">{{ endpoint.route }}</code>
                                </div>
                                
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $t(endpoint.title) }}</h3>

                                <!-- Code Examples Tabs -->
                                <div class="mt-6">
                                    <div class="border-b border-gray-300 mb-4">
                                        <div class="flex gap-x-1 overflow-x-auto">
                                            <button type="button"
                                                v-for="lang in ['curl', 'php', 'nodejs', 'python', 'java', 'ruby']"
                                                :key="lang"
                                                @click="selectedLanguage = lang"
                                                :class="[
                                                    'px-4 py-2 text-sm font-medium transition-colors border-b-2',
                                                    selectedLanguage === lang
                                                        ? 'border-primary text-primary'
                                                        : 'border-transparent text-gray-600 hover:text-gray-900'
                                                ]"
                                            >
                                                {{ lang.toUpperCase() }}
                                            </button>
                                        </div>
                                    </div>

                                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                        <pre class="text-green-400 text-xs font-mono whitespace-pre-wrap"><code>{{ getCodeExample(endpoint, selectedLanguage) }}</code></pre>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Base URL Info -->
                <div id="base-url" class="mt-12 bg-gray-100 rounded-2xl p-6 border border-gray-200 scroll-mt-24">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $t('Base URL') }}</h3>
                    <p class="text-gray-600 mb-2">
                        {{ $t('All API requests should be made to:') }}
                    </p>
                    <code class="text-gray-900 font-mono text-sm bg-white px-3 py-2 rounded-lg border border-gray-200 inline-block">{{ baseUrl }}/api</code>
                </div>
                </div>
            </div>
        </section>
    </FrontendLayout>
</template>

<script setup>
    import { ref, computed } from 'vue';
    import { useI18n } from 'vue-i18n';
    import { resolveApiDocExample } from '../../Utils/apiDocumentationExamples.js';
    import FrontendLayout from './FrontendLayout.vue';
    const { t } = useI18n();

    const props = defineProps(['companyConfig', 'pages', 'apirequests']);

    const selectedLanguage = ref('curl');
    
    const baseUrl = computed(() => {
        return window.location.origin;
    });

    const getCodeExample = (endpoint, language) => {
        if (!endpoint.request || !endpoint.request[language]) {
            return '// ' + t('Code example not available');
        }
        
        return resolveApiDocExample(endpoint.request[language], baseUrl.value);
    };

    const scrollToSection = (sectionId) => {
        const element = document.getElementById(sectionId);
        if (element) {
            const offset = 100; // Account for sticky header
            const elementPosition = element.getBoundingClientRect().top;
            const offsetPosition = elementPosition + window.pageYOffset - offset;

            window.scrollTo({
                top: offsetPosition,
                behavior: 'smooth'
            });
        }
    };
</script>
