<template>
    <FrontendLayout :companyConfig="props.companyConfig" :pages="props.pages">

        <!-- Hero Section -->
        <section class="relative px-5 md:px-10 lg:px-20 pt-32 pb-4 2xl:px-60 bg-gradient-section">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-3xl md:text-6xl leading-[1.3] font-semibold mb-6 text-gray-900">
                    {{ $t('Choose the Perfect') }}<br>{{ $t('Plan') }}
                </h1>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Whether you\'re an individual, a small team, or a growing enterprise, we have a plan that aligns perfectly with your goals') }}
                </p>
                <div class="flex flex-wrap justify-center gap-4 text-sm text-gray-500">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                        <span>{{ $t('Paid plans') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                        <span>{{ $t('Secure payment required') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-secondary" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2l4-4m6 2a9 9 0 11-18 0a9 9 0 0118 0z"/></svg>
                        <span>{{ $t('Cancel anytime') }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section class="relative px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div>
                <div class="relative">
                    <div class="text-center">
                        <!-- Monthly/Yearly Toggle -->
                        <div class="flex justify-center items-center gap-4 mb-8">
                            <span :class="{'text-gray-900 font-medium': billingPeriod === 'monthly', 'text-gray-500': billingPeriod === 'yearly'}" class="text-sm transition-colors">
                                {{ $t('Monthly') }}
                            </span>
                            <button type="button" 
                                @click="toggleBillingPeriod" 
                                class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                :class="billingPeriod === 'yearly' ? 'bg-primary' : 'bg-gray-300'"
                            >
                                <span 
                                    class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200 ease-in-out"
                                    :class="billingPeriod === 'yearly' ? 'translate-x-6' : 'translate-x-1'"
                                ></span>
                            </button>
                            <span :class="{'text-gray-900 font-medium': billingPeriod === 'yearly', 'text-gray-500': billingPeriod === 'monthly'}" class="text-sm transition-colors">
                                {{ $t('Yearly') }}
                            </span>
                            <span v-if="billingPeriod === 'yearly'" class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                {{ $t('Save 20%') }}
                            </span>
                        </div>
                    </div>

                    <div class="grid xl:grid-cols-3 md:grid-cols-2 lg:grid-cols-3 grid-cols-1 gap-6 mt-20">
                        <div v-for="(item, index) in filteredPlans" :key="index" class="relative">
                            <div class="transition-all duration-300 h-full flex flex-col">
                                <!-- Popular Badge -->
                                <div v-if="index === 1" class="pointer-events-none absolute inset-x-0 -top-4 z-20 flex justify-center sm:-top-5">
                                    <span class="inline-flex items-center justify-center rounded-full bg-primary px-4 py-1 text-xs font-semibold text-white shadow-[0_8px_16px_rgba(59,91,255,0.28)] ring-1 ring-white/70">
                                        {{ $t('Most Popular') }}
                                    </span>
                                </div>
                                
                                <div class="bg-white border-2 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 h-full flex flex-col"
                                    :class="index === 1 ? 'border-primary md:scale-105 origin-top' : 'border-gray-200 hover:border-primary/50'">
                                    <div class="p-8 pb-6">
                                        <!-- Plan Name -->
                                        <div class="mb-6">
                                            <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ getPlanName(item) }}</h2>
                                            <p class="text-sm text-gray-500">{{ planSubtitle(item) }}</p>
                                        </div>
                                        
                                        <!-- Price -->
                                        <div class="mb-6">
                                            <div class="flex items-baseline">
                                                <span class="text-gray-600 text-lg font-medium">{{ props.currency ?? '$' }}</span>
                                                <span class="text-5xl font-bold text-gray-900 ms-1">{{ item.price }}</span>
                                            </div>
                                            <p class="text-gray-500 text-sm mt-1">
                                                {{ billingPeriod === 'monthly' ? $t('Per month') : $t('Per year') }}
                                            </p>
                                        </div>
                                        
                                        <!-- CTA Button -->
                                        <Link 
                                            href="/signup" 
                                            class="block w-full text-center py-3 px-6 rounded-xl font-semibold transition-all duration-300 mb-6"
                                            :class="index === 1 
                                                ? 'bg-primary hover:bg-secondary text-white shadow-md hover:shadow-lg transform hover:scale-105' 
                                                : 'bg-gray-100 hover:bg-gray-200 text-gray-900 border border-gray-300 hover:border-primary'"
                                        >
                                            {{ $t('Select Plan') }}
                                        </Link>
                                    </div>
                                    
                                    <!-- Features List -->
                                    <div class="px-8 pb-8 pt-0 flex-1">
                                        <div class="border-t border-gray-200 pt-6">
                                            <h3 class="text-sm font-semibold text-gray-900 mb-4 uppercase tracking-wide">{{ $t('What\'s included') }}</h3>
                                            <div class="space-y-3">
                                                <div
                                                    v-for="(feature, featureIndex) in planFeatureList(item)"
                                                    :key="`plan-feature-${item.uuid || index}-${featureIndex}`"
                                                    class="flex min-h-[2rem] items-start gap-3"
                                                >
                                                    <div class="flex-shrink-0 mt-0.5">
                                                        <svg v-if="feature.available" class="w-5 h-5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        <svg v-else class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </div>
                                                    <span class="text-sm" :class="feature.available ? 'text-gray-700' : 'text-gray-400'">{{ feature.label }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQs Section -->
        <section v-if="props.faqs && props.faqs.data && props.faqs.data.length > 0" class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60">
            <div class="text-center">
                <div class="flex justify-center mb-2">
                    <div class="bg-primary-opacity-10 text-primary py-1 px-4 inline-block rounded-full mb-4">
                        <div class="flex items-center gap-2">
                            <div>{{ $t('FAQs') }}</div>
                        </div>
                    </div>
                </div>
                <h2 class="text-3xl md:text-5xl font-bold mb-4">{{ $t('Frequently asked questions') }}</h2>
                <div class="flex justify-center">
                    <h3 class="text-center text-[18px] mt-5 w-[32em]">{{ $t('Learn how our platform works and find solutions to common queries. Need more help? Reach out to our support team for personalized assistance') }}</h3>
                </div>
            </div>

            <div class="mt-14 lg:w-3/4 lg:mx-auto 2xl:w-2/3 space-y-2 md:px-20">
                <div v-for="(item, index) in props.faqs?.data" :key="index" class="border border-gray-300 rounded-lg">
                    <button type="button" @click="selectedFaq = selectedFaq === index+1 ? null : index+1" class="inline-flex p-5 items-center justify-between w-full text-start transition">
                        <div class="font-medium text-gray-900" v-html="item.question"></div>
                        <div>
                            <svg v-if="selectedFaq != index+1" xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M16.75 11.989a1.82 1.82 0 0 1-.57 1.36l-6.82 6.1a1.27 1.27 0 0 1-.65.31h-.19a1.3 1.3 0 0 1-.52-.1a1.23 1.23 0 0 1-.54-.47a1.19 1.19 0 0 1-.21-.68v-13a1.2 1.2 0 0 1 .21-.69a1.23 1.23 0 0 1 1.25-.56c.24.039.464.143.65.3l6.76 6.09c.19.162.344.363.45.59c.114.234.175.49.18.75"/></svg>
                            <svg v-else xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24"><path fill="currentColor" d="M19.696 8.72a1.22 1.22 0 0 1-.3.64l-6.09 6.76a1.85 1.85 0 0 1-.58.46a1.7 1.7 0 0 1-1.42.03a1.75 1.75 0 0 1-.62-.42l-6.1-6.83a1.28 1.28 0 0 1-.31-.64a1.31 1.31 0 0 1 .56-1.26a1.36 1.36 0 0 1 .68-.21h13a1.293 1.293 0 0 1 1.15.76c.081.228.092.476.03.71"/></svg>
                        </div>
                    </button>
                    <div class="w-full overflow-hidden transition-[height] duration-300" :class="selectedFaq === index+1 ? '' : 'hidden'">
                        <div class="text-gray-600 pt-3 p-5" v-html="item.answer"></div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-14">
                <p class="inline-flex flex-wrap gap-1 bg-primary-opacity-10 text-sm text-primary rounded-full py-2 px-5">
                    {{ $t('Still have unanswered questions?') }}
                    <a :href="'mailto:' + props.companyConfig.email" class="hover:text-primary hover:underline transition-all">{{ $t('Contact Us') }}</a>
                </p>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="px-5 md:px-10 lg:px-20 py-20 2xl:px-60 bg-gradient-cta">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-3xl md:text-5xl font-bold mb-6 text-gray-900">
                    {{ $t('Ready to Get Started?') }}
                </h2>
                <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                    {{ $t('Join thousands of businesses already using our platform to streamline their communication and grow their customer base.') }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <Link href="/signup" class="px-6 py-3 bg-primary hover:bg-secondary text-white rounded-xl text-base font-semibold transition-all duration-300 shadow-md hover:shadow-lg transform hover:scale-105">
                        {{ $t('Subscribe now') }}
                    </Link>
                    <a v-if="props.companyConfig && props.companyConfig.book_a_demo_link" :href="props.companyConfig.book_a_demo_link" target="_blank" rel="noopener noreferrer" class="px-6 py-3 border-2 border-gray-300 hover:border-primary text-gray-700 hover:text-primary rounded-xl text-base font-semibold transition-all duration-300 bg-white">
                        {{ $t('Book a Demo') }}
                    </a>
                </div>
            </div>
        </section>

    </FrontendLayout>
</template>

<script setup>
    import { ref, computed } from 'vue';
    import { Link, usePage } from "@inertiajs/vue3";
    import { useI18n } from 'vue-i18n';
    import FrontendLayout from './FrontendLayout.vue';

    const props = defineProps(['addons', 'enable_ai_billing', 'plans', 'companyConfig', 'currency', 'pages', 'faqs']);
    const { t } = useI18n();
    const page = usePage();
    const addonLabelKeyMap = {
        'Flow builder': 'Flow Builder',
    };
    const currentLocale = computed(() => String(page.props.currentLanguage ?? 'en').toLowerCase());

    const parseMetadata = (value) => {
        if (!value) {
            return null;
        }

        if (typeof value === 'object') {
            return value;
        }

        if (typeof value === 'string') {
            try {
                const parsed = JSON.parse(value);
                return typeof parsed === 'object' && parsed !== null ? parsed : null;
            } catch (error) {
                return null;
            }
        }

        return null;
    };

    const getDetail = (value, key) => {
        const item = parseMetadata(value);
        return item?.[key] ?? null;
    };

    const normalizeAddonFlag = (value) => {
        if (typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'number') {
            return value === 1;
        }

        if (typeof value === 'string') {
            return ['1', 'true', 'yes', 'on'].includes(value.trim().toLowerCase());
        }

        return false;
    };

    const firstFilled = (...values) => {
        for (const value of values) {
            if (typeof value === 'string' && value.trim() !== '') {
                return value.trim();
            }
        }

        return '';
    };

    const getPlanName = (item) => {
        const useArabic = currentLocale.value.startsWith('ar');

        if (useArabic) {
            return firstFilled(item?.display_name, item?.name_ar, item?.name, item?.name_en);
        }

        return firstFilled(item?.display_name, item?.name_en, item?.name, item?.name_ar);
    };

    const getPlanKey = (item) => {
        const metadata = parseMetadata(item?.metadata) ?? {};
        const fromMetadata = typeof metadata?.commercial_plan_key === 'string' ? metadata.commercial_plan_key.trim().toLowerCase() : '';
        const fromName = firstFilled(item?.name_en, item?.name, item?.name_ar).toLowerCase();

        if (['starter', 'growth', 'business'].includes(fromMetadata)) {
            return fromMetadata;
        }

        if (fromName.includes('growth')) {
            return 'growth';
        }

        if (fromName.includes('business')) {
            return 'business';
        }

        return 'starter';
    };

    const localizedPlanText = (texts, item) => {
        const key = getPlanKey(item);
        const useArabic = currentLocale.value.startsWith('ar');

        return texts?.[key]?.[useArabic ? 'ar' : 'en'] ?? texts?.starter?.[useArabic ? 'ar' : 'en'] ?? '';
    };

    const planSubtitle = (item) => localizedPlanText({
        starter: {
            ar: 'لبداية منظمة على واتساب بشكل منظم',
            en: 'For launching an organized WhatsApp workspace',
        },
        growth: {
            ar: 'للفرق التي تحتاج أتمتة ونمو أسرع',
            en: 'For teams that need automation and faster growth',
        },
        business: {
            ar: 'للعمليات متعددة الفرق على واتساب',
            en: 'For multi-team WhatsApp operations',
        },
    }, item);

    const resolveAddonLabelKey = (key) => addonLabelKeyMap[key] ?? key;

    const filteredAddons = (item) => {
        const metadata = parseMetadata(item?.metadata) ?? {};

        const addons = metadata?.addons;

        if (!addons || typeof addons !== 'object') {
            return {};
        }
        
        return Object.entries(props.addons ?? {}).reduce((acc, [key, value]) => {
            if ((Number(value) === 1 || normalizeAddonFlag(value)) && Object.prototype.hasOwnProperty.call(addons, key)) {
                acc[key] = normalizeAddonFlag(addons[key]);
            }

            return acc;
        }, {});
    };

    const getCustomFeatures = (item) => {
        const rawFeatures = getDetail(item?.metadata, 'custom_features');

        if (!Array.isArray(rawFeatures)) {
            return [];
        }

        const useArabic = currentLocale.value.startsWith('ar');

        return rawFeatures
            .map((feature) => {
                const textAr = typeof feature?.text_ar === 'string' ? feature.text_ar.trim() : '';
                const textEn = typeof feature?.text_en === 'string' ? feature.text_en.trim() : '';

                return useArabic ? firstFilled(textAr, textEn) : firstFilled(textEn, textAr);
            })
            .filter((feature) => feature !== '');
    };

    const formatPlanLimit = (value) => String(value) === '-1' ? t('Unlimited') : value;

    const limitFeature = (metadata, key, labelKey) => {
        const value = metadata?.[key];
        const available = value !== null && value !== undefined && value !== '' && Number(value) !== 0;

        return {
            label: available ? `${formatPlanLimit(value)} ${t(labelKey)}` : t(labelKey),
            available,
        };
    };

    const addonFeature = (item, key) => {
        const metadata = parseMetadata(item?.metadata) ?? {};
        const addons = metadata?.addons && typeof metadata.addons === 'object' ? metadata.addons : {};
        const visibleAddons = filteredAddons(item);
        const value = Object.prototype.hasOwnProperty.call(visibleAddons, key) ? visibleAddons[key] : addons[key];

        return {
            label: t(resolveAddonLabelKey(key)),
            available: normalizeAddonFlag(value),
        };
    };

    const advancedFlowBuilderFeature = (metadata) => ({
        label: t('Advanced Flow Builder'),
        available: normalizeAddonFlag(metadata?.flow_builder_advanced_enabled),
    });

    const serviceFeatureText = (item, type) => localizedPlanText({
        starter: {
            ar: {
                training: 'تهيئة وتدريب 30 دقيقة',
                support: 'دعم فني خلال أوقات العمل',
                setup: 'ربط WhatsApp Cloud API الأساسي',
            }[type],
            en: {
                training: '30-minute onboarding and training',
                support: 'Business-hours technical support',
                setup: 'Basic WhatsApp Cloud API setup',
            }[type],
        },
        growth: {
            ar: {
                training: 'إعداد وتدريب 60 دقيقة',
                support: 'دعم أولوية خلال أوقات العمل',
                setup: 'مساعدة في الردود الجاهزة والحملات',
            }[type],
            en: {
                training: '60-minute setup and training',
                support: 'Priority business-hours support',
                setup: 'Canned replies and campaign assistance',
            }[type],
        },
        business: {
            ar: {
                training: 'جلستا تدريب للفريق',
                support: 'دعم أولوية أعلى',
                setup: 'مساعدة في الفلوهات وإدارة واتساب',
            }[type],
            en: {
                training: 'Two team training sessions',
                support: 'Higher-priority support',
                setup: 'Flows and WhatsApp operations assistance',
            }[type],
        },
    }, item);

    const separateServiceText = (item, type) => localizedPlanText({
        starter: {
            ar: {
                verification: 'توثيق الحساب خدمة منفصلة حسب متطلبات Meta',
                metaFees: 'رسوم رسائل WhatsApp غير مشمولة',
            }[type],
            en: {
                verification: 'Account verification is a separate service',
                metaFees: 'WhatsApp messaging fees are not included',
            }[type],
        },
    }, item);

    const planFeatureList = (item) => {
        const metadata = parseMetadata(item?.metadata) ?? {};

        return [
            limitFeature(metadata, 'campaign_limit', 'Campaigns'),
            limitFeature(metadata, 'message_limit', 'Messages'),
            limitFeature(metadata, 'contacts_limit', 'Contacts'),
            limitFeature(metadata, 'canned_replies_limit', 'Canned replies'),
            limitFeature(metadata, 'team_limit', 'Users'),
            limitFeature(metadata, 'branches_limit', 'Branches'),
            addonFeature(item, 'Embedded Signup'),
            addonFeature(item, 'AI Assistant'),
            addonFeature(item, 'Flow builder'),
            advancedFlowBuilderFeature(metadata),
            { label: serviceFeatureText(item, 'training'), available: true },
            { label: serviceFeatureText(item, 'support'), available: true },
            { label: serviceFeatureText(item, 'setup'), available: true },
            { label: separateServiceText(item, 'verification'), available: false },
            { label: separateServiceText(item, 'metaFees'), available: false },
        ].filter((feature) => feature.label !== '');
    };

    const billingPeriod = ref('monthly');
    const selectedFaq = ref(null);

    const toggleBillingPeriod = () => {
        billingPeriod.value = billingPeriod.value === 'monthly' ? 'yearly' : 'monthly';
    };

    const filteredPlans = computed(() => {
        return props.plans.filter(plan => plan.period === billingPeriod.value);
    });
</script>


